<?php
require (getenv('SYMCON_STUBS') ?: '/tmp/SymconStubs') . '/autoload.php';
set_error_handler(function ($severity, $message, $file, $line) {
    if ($severity === E_DEPRECATED) { return true; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
IPS\Kernel::reset();
$root = dirname(__DIR__);
$library = json_decode(file_get_contents($root . '/library.json'), true);
$manifest = json_decode(file_get_contents($root . '/Vitocal222CTile/module.json'), true);
IPS\ModuleLoader::loadSingleModule($root . '/Vitocal222CTile', $library['id']);
$id = IPS_CreateInstance($manifest['id']);
$module = IPS\InstanceManager::getInstanceInterface($id);
$checks = 0;
function check($condition, $message) {
    global $checks;
    if (!$condition) { throw new Exception('FAIL: ' . $message); }
    $checks++;
}
function rejects($call, $message) {
    try { $call(); } catch (RuntimeException | InvalidArgumentException $e) { check(true, $message); return; }
    throw new Exception('FAIL: accepted ' . $message);
}
function snapshot($module) {
    preg_match('/const INITIAL = (.*);/', $module->GetVisualizationTile(), $m);
    return json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
}
check(count(snapshot($module)['missing']) === 11, 'missing sources reported');
check(IPS_GetProperty($id, 'ModeID') === 53049, 'heating mode default');
check(IPS_GetProperty($id, 'WaterTargetID') === 27660, 'water target default');
check(IPS_GetProperty($id, 'RoomTargetID') === 10939, 'room target default');
check(IPS_GetInstance($id)['InstanceVisualizationType'] === 1, 'HTML visualization');
$props = ['ModeID'=>3,'HeatingPumpID'=>0,'PrimaryPumpID'=>0,'RoomTargetID'=>2,'ReturnID'=>2,'ValveID'=>0,'CompressorID'=>0,'PrimaryFlowID'=>2,'FlowID'=>2,'WaterID'=>2,'WaterTargetID'=>2];
$ids = [];
foreach ($props as $prop => $type) {
    $v = IPS_CreateVariable($type); $ids[$prop] = $v;
    IPS_SetProperty($id, $prop, $v);
    SetValue($v, $type === 0 ? false : ($type === 3 ? 'heating' : 22.0));
}
$action = IPS_CreateScript(0);
IPS_SetScriptContent($action, '<?php $GLOBALS["captured"] = $_IPS;'); // no fake acknowledgement
foreach (['ModeID','RoomTargetID','WaterTargetID'] as $prop) IPS_SetVariableCustomAction($ids[$prop], $action);
IPS_SetVariableCustomPresentation($ids['ModeID'], ['PRESENTATION'=>VARIABLE_PRESENTATION_ENUMERATION,'OPTIONS'=>json_encode([['Value'=>'heating','Caption'=>'Heizen + Warmwasser'],['Value'=>'water','Caption'=>'Nur Warmwasser']])]);
foreach (['RoomTargetID'=>[10,30,0.5],'WaterTargetID'=>[30,60,1]] as $prop=>$range) {
    $profile = 'Fixture.' . $prop; IPS_CreateVariableProfile($profile,2);
    IPS_SetVariableProfileValues($profile,...$range);
    IPS_SetVariableProfileText($profile,'',' °C');
    IPS_SetVariableCustomProfile($ids[$prop],$profile);
}
SetValue($ids['WaterTargetID'],50.0);
SetValue($ids['WaterID'],45.6);SetValue($ids['FlowID'],27.7);SetValue($ids['ReturnID'],27.4);SetValue($ids['PrimaryFlowID'],10.0);
IPS_CreateVariableProfile('Fixture.Pump',0);
IPS_SetVariableProfileAssociation('Fixture.Pump',0,'Aus','',-1); IPS_SetVariableProfileAssociation('Fixture.Pump',1,'An','',-1);
foreach (['HeatingPumpID','PrimaryPumpID','CompressorID'] as $prop) IPS_SetVariableCustomProfile($ids[$prop],'Fixture.Pump');
IPS_CreateVariableProfile('Fixture.Valve',0);
IPS_SetVariableProfileAssociation('Fixture.Valve',0,'Heizung','',-1);IPS_SetVariableProfileAssociation('Fixture.Valve',1,'Warmwasser','',-1);
IPS_SetVariableCustomProfile($ids['ValveID'],'Fixture.Valve');
IPS_ApplyChanges($id);
$s=snapshot($module);
check(count($s['missing'])===0,'all eleven values read including booleans');
check(count(IPS_GetReferenceList($id))===11,'all references');
check(IPS_GetInstance($id)['InstanceStatus']===102,'active');
check($s['values']['valve']['formatted']==='Heizung','valve uses source labels');
check($s['mode']['writable'],'string enumeration writable');
$module->RequestAction('ChangeMode','water');
check($GLOBALS['captured']['VARIABLE']===$ids['ModeID'] && $GLOBALS['captured']['VALUE']==='water','exact string action');
check(GetValue($ids['ModeID'])==='heating','no optimistic source changes');
foreach (['invalid',1,true,null,[]] as $value) rejects(fn()=>$module->RequestAction('ChangeMode',$value),'invalid enum');
$module->RequestAction('SetRoomTarget',22.5);
check($GLOBALS['captured']['VARIABLE']===$ids['RoomTargetID'] && $GLOBALS['captured']['VALUE']===22.5,'room dispatch');
$module->RequestAction('SetWaterTarget',51);
check($GLOBALS['captured']['VARIABLE']===$ids['WaterTargetID'] && $GLOBALS['captured']['VALUE']===51.0,'water float dispatch');
check(GetValue($ids['WaterTargetID'])===50.0,'no optimistic temperature change');
foreach ([-1,31,22.3,'22',null,[],true,INF,NAN] as $value) rejects(fn()=>$module->RequestAction('SetRoomTarget',$value),'invalid target');
IPS_SetVariableCustomAction($ids['RoomTargetID'],1);
check(!snapshot($module)['controls']['roomTarget']['writable'],'disabled action');
rejects(fn()=>$module->RequestAction('SetRoomTarget',22),'disabled target action');
IPS_SetVariableCustomAction($ids['RoomTargetID'],$action);
IPS_SetVariableProfileValues('Fixture.RoomTargetID',10,20,0.5);
rejects(fn()=>$module->RequestAction('SetRoomTarget',22),'range re-read server side');
IPS_SetVariableProfileValues('Fixture.RoomTargetID',10,30,0);
check(!snapshot($module)['controls']['roomTarget']['writable'],'zero step is read only');
IPS_SetVariableProfileValues('Fixture.RoomTargetID',10,30,0.5);
IPS_SetVariableCustomPresentation($ids['RoomTargetID'],['PRESENTATION'=>VARIABLE_PRESENTATION_SLIDER]);
check(!snapshot($module)['controls']['roomTarget']['writable'],'modern presentation never falls back to stale profile');
IPS_SetVariableCustomPresentation($ids['RoomTargetID'],[]);
IPS_SetVariableProfileAssociation('Fixture.RoomTargetID',22,'Preset','',-1);
check(!snapshot($module)['controls']['roomTarget']['writable'],'enumerated numeric profile not treated as free range');
IPS_SetVariableProfileAssociation('Fixture.RoomTargetID',22,'','',-1);
IPS_SetVariableCustomPresentation($ids['ModeID'], ['PRESENTATION'=>VARIABLE_PRESENTATION_ENUMERATION,'OPTIONS'=>json_encode([['Value'=>'heating','Caption'=>'Heizen + Warmwasser']])]);
rejects(fn()=>$module->RequestAction('ChangeMode','water'),'removed option');
check(snapshot($module)['actionError']!=='','action error in SDK payload');
$module->RequestAction('ChangeMode','heating');
check(snapshot($module)['actionError']==='','next action clears error');
IPS_SetVariableCustomPresentation($ids['ModeID'], ['PRESENTATION'=>VARIABLE_PRESENTATION_ENUMERATION,'OPTIONS'=>json_encode([['Value'=>'heating','Caption'=>'Heizen + Warmwasser'],['Value'=>'water','Caption'=>'Nur Warmwasser']])]);
$integer=IPS_CreateVariable(1);IPS_CreateVariableProfile('Fixture.Integer',1);IPS_SetVariableProfileValues('Fixture.Integer',10,30,1);IPS_SetVariableCustomProfile($integer,'Fixture.Integer');IPS_SetVariableCustomAction($integer,$action);IPS_SetProperty($id,'RoomTargetID',$integer);IPS_ApplyChanges($id);
$module->RequestAction('SetRoomTarget',23.0);
check($GLOBALS['captured']['VALUE']===23,'integer preserved');
rejects(fn()=>$module->RequestAction('SetRoomTarget',23.5),'fraction rejected for integer');
IPS_SetProperty($id,'RoomTargetID',$ids['RoomTargetID']);IPS_ApplyChanges($id);IPS_ApplyChanges($id);
check(count(IPS_GetReferenceList($id))===11,'repeatable apply');
SetValue($ids['HeatingPumpID'],true);$module->MessageSink(time(),$ids['HeatingPumpID'],VM_UPDATE,[true,true]);
check(snapshot($module)['values']['heatingPump']['value']===true,'live boolean update');
// HTML must keep profile captions as data.
IPS_SetVariableCustomPresentation($ids['ModeID'], ['PRESENTATION'=>VARIABLE_PRESENTATION_ENUMERATION,'OPTIONS'=>json_encode([['Value'=>'heating','Caption'=>'</script><img src=x onerror=alert(1)>']])]);
check(strpos($module->GetVisualizationTile(),'</script><img')===false,'initial script escaping');
IPS_SetVariableCustomPresentation($ids['ModeID'], ['PRESENTATION'=>VARIABLE_PRESENTATION_ENUMERATION,'OPTIONS'=>json_encode([['Value'=>'heating','Caption'=>'Heizen + Warmwasser'],['Value'=>'water','Caption'=>'Nur Warmwasser']])]);
if(!is_dir(__DIR__.'/output')) mkdir(__DIR__.'/output',0777,true);
file_put_contents(__DIR__.'/output/tile.html',$module->GetVisualizationTile());
file_put_contents(__DIR__.'/output/snapshot.json',json_encode(snapshot($module),JSON_THROW_ON_ERROR));
IPS_DeleteVariable($ids['PrimaryFlowID']);$module->MessageSink(time(),$ids['PrimaryFlowID'],VM_DELETE,[]);
check(count(snapshot($module)['missing'])===1,'deleted source handled');
check(IPS_GetInstance($id)['InstanceStatus']===104,'missing source status');
echo "PASS: $checks module assertions (official SymconStubs)\n";
