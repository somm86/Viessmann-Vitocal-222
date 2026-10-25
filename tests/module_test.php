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
check(count(snapshot($module)['missing']) === 9, 'missing sources reported');
check(IPS_GetProperty($id, 'ModeID') === 53049, 'heating mode default');
check(IPS_GetProperty($id, 'ArchiveID') === 0, 'archive auto-detection default');
$form = json_decode($module->GetConfigurationForm(), true);
check(!in_array('RoomTargetID', array_column($form['elements'], 'name'), true), 'room setpoint selector removed');
check(!in_array('WaterTargetID', array_column($form['elements'], 'name'), true), 'water setpoint selector removed');
check(IPS_GetInstance($id)['InstanceVisualizationType'] === 1, 'HTML visualization');
$props = ['ModeID'=>3,'HeatingPumpID'=>0,'PrimaryPumpID'=>0,'ReturnID'=>2,'ValveID'=>0,'CompressorID'=>0,'PrimaryFlowID'=>2,'FlowID'=>2,'WaterID'=>2];
$ids = [];
foreach ($props as $prop => $type) {
    $v = IPS_CreateVariable($type); $ids[$prop] = $v;
    IPS_SetProperty($id, $prop, $v);
    SetValue($v, $type === 0 ? false : ($type === 3 ? 'heating' : 22.0));
}
$action = IPS_CreateScript(0);
IPS_SetScriptContent($action, '<?php $GLOBALS["captured"] = $_IPS;'); // no fake acknowledgement
foreach (['ModeID'] as $prop) IPS_SetVariableCustomAction($ids[$prop], $action);
IPS_SetVariableCustomPresentation($ids['ModeID'], ['PRESENTATION'=>VARIABLE_PRESENTATION_ENUMERATION,'OPTIONS'=>json_encode([['Value'=>'heating','Caption'=>'Heizen + Warmwasser'],['Value'=>'water','Caption'=>'Nur Warmwasser']])]);
SetValue($ids['WaterID'],45.6);SetValue($ids['FlowID'],27.7);SetValue($ids['ReturnID'],27.4);SetValue($ids['PrimaryFlowID'],10.0);
IPS_CreateVariableProfile('Fixture.Pump',0);
IPS_SetVariableProfileAssociation('Fixture.Pump',0,'Aus','',-1); IPS_SetVariableProfileAssociation('Fixture.Pump',1,'An','',-1);
foreach (['HeatingPumpID','PrimaryPumpID','CompressorID'] as $prop) IPS_SetVariableCustomProfile($ids[$prop],'Fixture.Pump');
IPS_CreateVariableProfile('Fixture.Valve',0);
IPS_SetVariableProfileAssociation('Fixture.Valve',0,'Heizung','',-1);IPS_SetVariableProfileAssociation('Fixture.Valve',1,'Warmwasser','',-1);
IPS_SetVariableCustomProfile($ids['ValveID'],'Fixture.Valve');
IPS_ApplyChanges($id);
$s=snapshot($module);
check(count($s['missing'])===0,'all nine values read including booleans');
check(count(IPS_GetReferenceList($id))===9,'all references');
check(IPS_GetInstance($id)['InstanceStatus']===102,'active');
check($s['values']['valve']['formatted']==='Heizung','valve uses source labels');
check($s['values']['mode']['formatted']==='Heizen + Warmwasser','mode is a formatted read-only status');
foreach (['water','heating','invalid',1,true,null,[]] as $value) rejects(fn()=>$module->RequestAction('ChangeMode',$value),'all operating-mode writes rejected');
check(!isset($GLOBALS['captured']),'configured source action never called');
check(GetValue($ids['ModeID'])==='heating','source mode not changed');
rejects(fn()=>$module->RequestAction('SetRoomTarget',22.5),'room setpoint route removed');
rejects(fn()=>$module->RequestAction('SetWaterTarget',51),'water setpoint route removed');
check(!isset($s['mode'],$s['controls']),'snapshot exposes no control configuration');
check(json_decode($module->GetDiagnostics(),true)['readOnly'],'diagnostics identify read-only module');
IPS_ApplyChanges($id);IPS_ApplyChanges($id);
check(count(IPS_GetReferenceList($id))===9,'repeatable apply');
SetValue($ids['HeatingPumpID'],true);$module->MessageSink(time(),$ids['HeatingPumpID'],VM_UPDATE,[true,true]);
check(snapshot($module)['values']['heatingPump']['value']===true,'live boolean update');
// HTML must keep profile captions as data.
IPS_SetVariableCustomPresentation($ids['ModeID'], ['PRESENTATION'=>VARIABLE_PRESENTATION_ENUMERATION,'OPTIONS'=>json_encode([['Value'=>'heating','Caption'=>'</script><img src=x onerror=alert(1)>']])]);
check(strpos($module->GetVisualizationTile(),'</script><img')===false,'initial script escaping');
IPS_SetVariableCustomPresentation($ids['ModeID'], ['PRESENTATION'=>VARIABLE_PRESENTATION_ENUMERATION,'OPTIONS'=>json_encode([['Value'=>'heating','Caption'=>'Heizen + Warmwasser'],['Value'=>'water','Caption'=>'Nur Warmwasser']])]);

// Real Archive Control stub calls; only synthetic fixture writes below.
IPS\ModuleLoader::loadSingleModule((getenv('SYMCON_STUBS') ?: '/tmp/SymconStubs') . '/CoreStubs/ArchiveControl', $library['id']);
$archive = IPS_CreateInstance('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
$start = time() - 86400; $end = time();
rejects(fn()=>$module->GetHistory('water',$start,$end),'logging missing');
foreach (['waterTarget','roomTarget','mode','compressor','NotAKey'] as $key) rejects(fn()=>$module->GetHistory($key,$start,$end),'history source allowlist');
foreach ([[-1,$end],[$end,$start],[$end,$end],[$start,$end+1000],[$end-2678401,$end]] as $range) {
    rejects(fn()=>$module->GetHistory('water',...$range),'invalid history window');
}
foreach (['WaterID','FlowID','ReturnID','PrimaryFlowID'] as $prop) {
    AC_SetLoggingStatus($archive,$ids[$prop],true);
    AC_AddLoggedValues($archive,$ids[$prop],[['TimeStamp'=>$end-3600,'Value'=>21.5],['TimeStamp'=>$end-1800,'Value'=>23.0],['TimeStamp'=>$end-100,'Value'=>22.0]]);
}
$h=json_decode($module->GetHistory('water',$start,$end),true);
check($h['resolution']==='raw' && count($h['points'])===3,'actual archive rows retrieved');
check(array_column($h['points'],'value')===[21.5,23,22],'rows sorted ascending, not current value');
check($h['variableID']===$ids['WaterID'],'mapped source used');
foreach (['primaryFlow','flow','return'] as $key) check(count(json_decode($module->GetHistory($key,$start,$end),true)['points'])===3,'each temperature has archive');
$empty=json_decode($module->GetHistory('water',$end-90,$end),true);
check($empty['points']===[],'empty history never filled with current temperature');
AC_StubsAddAggregatedValues($archive,$ids['WaterID'],0,[['TimeStamp'=>$end-3600*25,'Duration'=>3600,'Avg'=>44.5,'Min'=>42.0,'Max'=>46.0]]);
$aggregated=json_decode($module->GetHistory('water',$end-604800,$end),true);
check($aggregated['resolution']==='hourly' && $aggregated['points'][0]['value']===44.5,'hourly averages for long window');
IPS_SetProperty($id,'ArchiveID',$action);IPS_ApplyChanges($id);
rejects(fn()=>$module->GetHistory('water',$start,$end),'non-archive instance rejected');
$otherArchive=IPS_CreateInstance('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
IPS_SetProperty($id,'ArchiveID',$otherArchive);IPS_ApplyChanges($id);
rejects(fn()=>$module->GetHistory('water',$start,$end),'explicit archive never silently replaced');
IPS_SetProperty($id,'ArchiveID',$archive);IPS_ApplyChanges($id);
check(in_array($archive,IPS_GetReferenceList($id),true),'explicit archive reference');
AC_SetAggregationType($archive,$ids['WaterID'],1);
rejects(fn()=>$module->GetHistory('water',$start,$end),'counter aggregation rejected');
AC_SetAggregationType($archive,$ids['WaterID'],0);
function lastMessage($module) {
    $property = new ReflectionProperty(IPSModule::class, 'messages');
    $messages = $property->getValue($module);$last = end($messages);
    return json_decode($last['Data'][0],true,512,JSON_THROW_ON_ERROR);
}
$module->RequestAction('RequestHistory',json_encode(['requestID'=>'test_1','key'=>'water','start'=>$start,'end'=>$end]));
$reply=lastMessage($module);
check($reply['kind']==='history' && $reply['requestID']==='test_1' && count($reply['points'])===3,'correlated void SDK reply');
check(!isset(snapshot($module)['actionError']),'no obsolete operating-mode error state');
$module->RequestAction('RequestHistory',json_encode(['requestID'=>'bad_query','key'=>'water','start'=>(string)$start,'end'=>$end]));
check(lastMessage($module)['error']!=='' && lastMessage($module)['requestID']==='bad_query','invalid query gives correlated error');
$module->RequestAction('RequestHistory','{"requestID":"<script>"}');
check(lastMessage($module)['error']!=='','invalid request ID');
$module->RequestAction('RequestHistory','not-json');check(lastMessage($module)['error']!=='','malformed JSON handled');
AC_SetLoggingStatus($archive,$ids['WaterID'],false);
$module->RequestAction('RequestHistory',json_encode(['requestID'=>'off','key'=>'water','start'=>$start,'end'=>$end]));
check(strpos(lastMessage($module)['error'],'Archivierung')!==false,'missing archive logging message');
AC_SetLoggingStatus($archive,$ids['WaterID'],true);
// Archive API returns at most 10,000 rows. Never silently display a truncated window.
$many=[];for($i=0;$i<10000;$i++)$many[]=['TimeStamp'=>$end-90,'Value'=>20+$i/10000];
AC_AddLoggedValues($archive,$ids['FlowID'],$many);
rejects(fn()=>$module->GetHistory('flow',$start,$end),'truncation refused');
$wrong=IPS_CreateVariable(3);IPS_SetProperty($id,'WaterID',$wrong);IPS_ApplyChanges($id);
rejects(fn()=>$module->GetHistory('water',$start,$end),'nonnumeric history variable rejected');
IPS_SetProperty($id,'WaterID',$ids['WaterID']);IPS_SetProperty($id,'ArchiveID',0);IPS_ApplyChanges($id);
check(count(IPS_GetReferenceList($id))===9,'removed explicit archive reference');
check(strpos($module->GetVisualizationTile(),'SetRoomTarget')===false && strpos($module->GetVisualizationTile(),'SetWaterTarget')===false,'no setpoint controls in HTML');
check(strpos($module->GetVisualizationTile(),'ChangeMode')===false,'no mode action in browser');
if(!is_dir(__DIR__.'/output')) mkdir(__DIR__.'/output',0777,true);
file_put_contents(__DIR__.'/output/tile.html',$module->GetVisualizationTile());
file_put_contents(__DIR__.'/output/snapshot.json',json_encode(snapshot($module),JSON_THROW_ON_ERROR));
file_put_contents(__DIR__.'/output/history.json',$module->GetHistory('water',$start,$end));
IPS_DeleteVariable($ids['PrimaryFlowID']);$module->MessageSink(time(),$ids['PrimaryFlowID'],VM_DELETE,[]);
check(count(snapshot($module)['missing'])===1,'deleted source handled');
check(IPS_GetInstance($id)['InstanceStatus']===104,'missing source status');
echo "PASS: $checks module assertions (official SymconStubs)\n";
