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
// Load the complete library, exactly including root-directory discovery.
IPS\ModuleLoader::loadLibrary($root . '/library.json');
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
check(IPS_GetProperty($id,'PowerID')===48059 && IPS_GetProperty($id,'TotalEnergyID')===58570,'user electrical source defaults');
check(IPS_GetProperty($id,'PowerUnit')==='W' && IPS_GetProperty($id,'TotalEnergyUnit')==='kWh','user electrical input units');
IPS_SetProperty($id,'PowerID',0);IPS_SetProperty($id,'TotalEnergyID',0);IPS_ApplyChanges($id);
check(count(snapshot($module)['missing']) === 11, 'missing sources reported');
check(IPS_GetProperty($id, 'ModeID') === 41758, 'heating mode default');
check(IPS_GetProperty($id, 'ArchiveID') === 0, 'archive auto-detection default');
$migration=IPS_CreateInstance($manifest['id']);IPS_SetProperty($migration,'ModeID',53049);IPS_SetProperty($migration,'FlowID',12345);IPS_ApplyChanges($migration);
check(IPS_GetProperty($migration,'ModeID')===41758 && IPS_GetProperty($migration,'FlowID')===12345,'migrate known old IDs and preserve custom sources');IPS_DeleteInstance($migration);
$form = json_decode($module->GetConfigurationForm(), true);
check(in_array('RoomTargetID', array_column($form['elements'], 'name'), true), 'room setpoint selector present');
check(in_array('WaterTargetID', array_column($form['elements'], 'name'), true), 'water setpoint selector present');
check(IPS_GetInstance($id)['InstanceVisualizationType'] === 1, 'HTML visualization');
$props = ['ModeID'=>3,'HeatingPumpID'=>0,'PrimaryPumpID'=>0,'ReturnID'=>2,'ValveID'=>0,'CompressorID'=>0,'PrimaryFlowID'=>2,'FlowID'=>2,'WaterID'=>2,'RoomTargetID'=>2,'WaterTargetID'=>2];
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
check($s['values']['mode']['formatted']==='Heizen + Warmwasser','mode is a formatted read-only status');
foreach (['invalid',1,true,null,[]] as $value) {
    $module->RequestAction('ChangeMode',$value);
    check(!isset($GLOBALS['captured']),'invalid mode does not dispatch');
}
$module->RequestAction('ChangeMode','water');
check($GLOBALS['captured']['VARIABLE']===$ids['ModeID'] && $GLOBALS['captured']['VALUE']==='water','mode routed to source action');
check(GetValue($ids['ModeID'])==='heating','no fabricated source mode acknowledgement');
unset($GLOBALS['captured']);
$module->RequestAction('SetRoomTarget',23);
check($GLOBALS['captured']['VARIABLE']===$ids['RoomTargetID'] && $GLOBALS['captured']['VALUE']===23,'room target routed to source action');
check(GetValue($ids['RoomTargetID'])===22.0,'no fabricated target acknowledgement');
unset($GLOBALS['captured']);
foreach ([-1,31,22.5,22.3,'22.5',NAN,INF,null] as $value) {
    $module->RequestAction('SetRoomTarget',$value);
    check(!isset($GLOBALS['captured']),'invalid room target does not dispatch');
}
IPS_SetVariableCustomAction($ids['RoomTargetID'],0);
$module->RequestAction('SetRoomTarget',23);
check(!isset($GLOBALS['captured']) && !snapshot($module)['controls']['roomTarget']['enabled'],'missing action disables control');
IPS_SetVariableCustomAction($ids['RoomTargetID'],$action);
IPS_CreateVariableProfile('Fixture.Room',2);IPS_SetVariableProfileValues('Fixture.Room',15,25,0.5);IPS_SetVariableCustomProfile($ids['RoomTargetID'],'Fixture.Room');
$module->RequestAction('SetRoomTarget',26);check(!isset($GLOBALS['captured']),'source profile limits override fallback range');
IPS_SetVariableCustomProfile($ids['RoomTargetID'],'');
$module->RequestAction('SetWaterTarget',51);
check($GLOBALS['captured']['VARIABLE']===$ids['WaterTargetID'] && $GLOBALS['captured']['VALUE']===51,'water target routed to source action');
unset($GLOBALS['captured']);
$module->RequestAction('SetWaterTarget',61);check(!isset($GLOBALS['captured']),'out of range water target rejected');
check(isset($s['controls']),'snapshot exposes controls');
check(!json_decode($module->GetDiagnostics(),true)['readOnly'],'diagnostics identify controls');
IPS_ApplyChanges($id);IPS_ApplyChanges($id);
check(count(IPS_GetReferenceList($id))===11,'repeatable apply');
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
foreach (['compressor'=>'CompressorID','valve'=>'ValveID'] as $key=>$prop) {
    AC_SetLoggingStatus($archive,$ids[$prop],true);
    AC_AddLoggedValues($archive,$ids[$prop],[['TimeStamp'=>$start-100,'Value'=>false],['TimeStamp'=>$end-3600,'Value'=>true],['TimeStamp'=>$end-100,'Value'=>false]]);
    $statusHistory=json_decode($module->GetHistory($key,$start,$end),true);
    check($statusHistory['resolution']==='status' && array_column($statusHistory['points'],'value')===[0,1,0],'raw boolean transitions '.$key);
    check($statusHistory['points'][0]['time']===$start,'status baseline at window start '.$key);
    AC_SetAggregationType($archive,$ids[$prop],2);
    check(json_decode($module->GetHistory($key,$start,$end),true)['points']===$statusHistory['points'],'boolean archive type preserves raw status history '.$key);
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
$failure=IPS_CreateScript(0);IPS_SetScriptContent($failure,'<?php throw new RuntimeException("Quelle nicht erreichbar");');
IPS_SetVariableCustomAction($ids['WaterTargetID'],$failure);
$module->Refresh();$module->RequestAction('SetWaterTarget',50);
check(lastMessage($module)['kind']==='control' && lastMessage($module)['error']==='Quelle nicht erreichbar','source action exception reported to tile');
IPS_SetVariableCustomAction($ids['WaterTargetID'],$action);$module->Refresh();
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
check(count(IPS_GetReferenceList($id))===11,'removed explicit archive reference');
check(strpos($module->GetVisualizationTile(),'SetRoomTarget')!==false && strpos($module->GetVisualizationTile(),'SetWaterTarget')!==false,'room and water controls in HTML');
check(strpos($module->GetVisualizationTile(),'ChangeMode')!==false,'mode action in browser');
// Electrical values use independent sources; missing optional sources do not break existing tiles.
check(IPS_GetProperty($id,'PowerID')===0 && IPS_GetProperty($id,'TotalEnergyID')===0,'electrical sources can be disabled');
check(snapshot($module)['energy']['power']['value']===null,'missing power never becomes zero');
$power=IPS_CreateVariable(2);$total=IPS_CreateVariable(2);$monthVariable=IPS_CreateVariable(2);
foreach (['Fixture.W'=>' W','Fixture.Wh'=>' Wh','Fixture.kWh'=>' kWh'] as $name=>$suffix) {
    IPS_CreateVariableProfile($name,2);IPS_SetVariableProfileText($name,'',$suffix);
}
IPS_SetVariableCustomProfile($power,'Fixture.W');IPS_SetVariableCustomProfile($total,'Fixture.Wh');IPS_SetVariableCustomProfile($monthVariable,'Fixture.kWh');
SetValue($power,2600.0);SetValue($total,1250000.0);SetValue($monthVariable,12.5);
IPS_SetProperty($id,'PowerID',$power);IPS_SetProperty($id,'TotalEnergyID',$total);IPS_SetProperty($id,'PowerUnit','auto');IPS_SetProperty($id,'TotalEnergyUnit','auto');IPS_ApplyChanges($id);
$s=snapshot($module);
check($s['energy']['power']['value']===2.6 && $s['energy']['power']['unit']==='kW','W converted to kW');
check($s['energy']['total']['value']===1250 && $s['energy']['total']['unit']==='kWh','Wh converted to kWh');
check(count(IPS_GetReferenceList($id))===13,'energy sources subscribed');
check($s['energy']['month']['value']===null,'monthly archive missing is not zero');
$zone=new DateTimeZone('Europe/Vienna');
$monthStart=(new DateTimeImmutable('now',$zone))->modify('first day of this month')->setTime(0,0)->getTimestamp();
$previousMonth=(new DateTimeImmutable('@'.$monthStart))->setTimezone($zone)->modify('-1 month')->getTimestamp();
$monthMethod = new ReflectionMethod(Vitocal222CTile::class, 'monthStart');
foreach ([['2026-03-31T12:00:00Z','2026-03-01T00:00:00+01:00'],['2026-10-31T12:00:00Z','2026-10-01T00:00:00+02:00'],['2027-01-05T12:00:00Z','2027-01-01T00:00:00+01:00'],['2028-02-29T12:00:00Z','2028-02-01T00:00:00+01:00']] as [$input,$expected]) {
    check($monthMethod->invoke($module,strtotime($input))===strtotime($expected),'DST, year and leap-month boundary ' . $input);
}
$now=time();$span=$now-$monthStart;
$p1=$monthStart+(int)($span/3);$p2=$monthStart+(int)($span*2/3);
AC_SetLoggingStatus($archive,$total,true);AC_SetAggregationType($archive,$total,1);
AC_AddLoggedValues($archive,$total,[['TimeStamp'=>$previousMonth-1,'Value'=>1150000.0],['TimeStamp'=>$previousMonth+600,'Value'=>1160000.0],['TimeStamp'=>$monthStart-1,'Value'=>1200000.0],['TimeStamp'=>$p1,'Value'=>1210000.0],['TimeStamp'=>$p2,'Value'=>1248000.0]]);
AC_StubsAddAggregatedValues($archive,$total,0,[['TimeStamp'=>$now-3600,'Duration'=>3600,'Avg'=>2500.0,'Min'=>1200000.0,'Max'=>1250000.0]]);
AC_StubsAddAggregatedValues($archive,$total,1,[['TimeStamp'=>$p1,'Duration'=>86400,'Avg'=>10000.0],['TimeStamp'=>$p2,'Duration'=>86400,'Avg'=>38000.0]]);
AC_SetLoggingStatus($archive,$power,true);
AC_AddLoggedValues($archive,$power,[['TimeStamp'=>$now-3600,'Value'=>2000.0],['TimeStamp'=>$now-100,'Value'=>2600.0]]);
AC_StubsAddAggregatedValues($archive,$power,0,[['TimeStamp'=>$now-25*3600,'Duration'=>3600,'Avg'=>1800.0]]);
$s=snapshot($module);
check($s['energy']['month']['value']===50,'calendar month is current total minus boundary total');
check($s['energy']['month']['baselineTime']===$monthStart-1,'boundary sample provenance');
check($s['energy']['month']['periodLabel']===(new DateTimeImmutable('@'.$monthStart))->setTimezone($zone)->format('m.Y'),'calendar month uses configured zone');
check($s['sourceCount']===13,'source footer count includes configured electrical sources');
$ph=json_decode($module->GetHistory('power',$now-86400,$now),true);
check($ph['unit']==='kW' && array_column($ph['points'],'value')===[2,2.6],'power graph normalized units');
$phLong=json_decode($module->GetHistory('power',$now-604800,$now),true);
check($phLong['resolution']==='hourly' && abs($phLong['points'][0]['value']-1.8)<1e-10,'power graph averages standard values');
$th=json_decode($module->GetHistory('total',$monthStart,$now),true);
check($th['unit']==='kWh' && array_column($th['points'],'value')===[10,38] && $th['resolution']==='consumptionDaily','counter graph reads daily consumption with Wh normalization');
$mh=json_decode($module->GetHistory('month',$monthStart,$now),true);
check($mh['resolution']==='consumptionDaily' && array_column($mh['points'],'value')===[10,38],'derived monthly graph uses total counter consumption intervals');
$cross=json_decode($module->GetHistory('month',max($monthStart-86400,$now-2678400),$now),true);
check($cross['resolution']==='consumptionDaily','counter consumption across months uses daily intervals');
$emptyCounter=json_decode($module->GetHistory('total',$now-7200,$now-3601),true);check($emptyCounter['points']===[],'missing counter aggregates never replaced with current meter reading');
$counterHour=json_decode($module->GetHistory('total',$now-86400,$now),true);
check($counterHour['resolution']==='consumptionHourly' && array_column($counterHour['points'],'value')===[2.5],'short counter chart uses hourly consumption, not cumulative meter levels');
IPS_SetVariableProfileText('Fixture.W','',' %');
check(snapshot($module)['energy']['power']['value']===null,'unknown source units not guessed');
IPS_SetProperty($id,'PowerUnit','W');IPS_ApplyChanges($id);
check(snapshot($module)['energy']['power']['value']===2.6,'explicit power input unit');
IPS_SetVariableProfileText('Fixture.W','',' W');IPS_SetProperty($id,'PowerUnit','auto');IPS_ApplyChanges($id);
SetValue($power,0.0);check(snapshot($module)['energy']['power']['value']===0,'real zero power preserved');
SetValue($power,-1.0);check(snapshot($module)['energy']['power']['value']===null,'negative consumption power rejected');SetValue($power,2600.0);
$reset=IPS_CreateVariable(2);IPS_SetVariableCustomProfile($reset,'Fixture.kWh');SetValue($reset,1250.0);AC_SetLoggingStatus($archive,$reset,true);
AC_AddLoggedValues($archive,$reset,[['TimeStamp'=>$monthStart-1,'Value'=>1000.0],['TimeStamp'=>$p1,'Value'=>1010.0],['TimeStamp'=>$p2,'Value'=>5.0],['TimeStamp'=>$monthStart+(int)($span*5/6),'Value'=>1200.0]]);
IPS_SetProperty($id,'TotalEnergyID',$reset);IPS_ApplyChanges($id);
check(snapshot($module)['energy']['month']['value']===null && strpos(snapshot($module)['energy']['month']['reason'],'Zähler')!==false,'mid-month reset detected even when final reading exceeds initial');
rejects(fn()=>$module->GetHistory('month',$monthStart,$now),'reset prevents misleading derived graph');
$noBase=IPS_CreateVariable(2);IPS_SetVariableCustomProfile($noBase,'Fixture.kWh');SetValue($noBase,20.0);AC_SetLoggingStatus($archive,$noBase,true);AC_AddLoggedValues($archive,$noBase,[['TimeStamp'=>$monthStart+100,'Value'=>10.0]]);
IPS_SetProperty($id,'TotalEnergyID',$noBase);IPS_ApplyChanges($id);
check(snapshot($module)['energy']['month']['value']===null,'no month boundary reading never replaced by first later reading');
IPS_SetProperty($id,'MonthlyEnergyID',$monthVariable);IPS_ApplyChanges($id);
check(snapshot($module)['energy']['month']['value']===12.5,'provided month variable takes precedence');
AC_SetLoggingStatus($archive,$monthVariable,true);AC_AddLoggedValues($archive,$monthVariable,[['TimeStamp'=>$now-100,'Value'=>12.0]]);
AC_SetAggregationType($archive,$monthVariable,1);
AC_StubsAddAggregatedValues($archive,$monthVariable,0,[['TimeStamp'=>$now-3600,'Duration'=>3600,'Avg'=>1.5]]);
$direct=json_decode($module->GetHistory('month',$now-86400,$now),true);
check($direct['points'][0]['value']===1.5 && $direct['unit']==='kWh' && $direct['resolution']==='consumptionHourly','provided monthly counter graph uses its own consumption aggregates');
$badCounter=IPS_CreateVariable(2);IPS_SetVariableCustomProfile($badCounter,'Fixture.kWh');SetValue($badCounter,1.0);
AC_SetLoggingStatus($archive,$badCounter,true);AC_SetAggregationType($archive,$badCounter,1);
AC_StubsAddAggregatedValues($archive,$badCounter,0,[['TimeStamp'=>$now-3600,'Duration'=>3600,'Avg'=>-1.0]]);
IPS_SetProperty($id,'MonthlyEnergyID',$badCounter);IPS_ApplyChanges($id);
rejects(fn()=>$module->GetHistory('month',$now-86400,$now),'negative counter aggregate rejected');
IPS_SetProperty($id,'TotalEnergyID',$total);IPS_SetProperty($id,'MonthlyEnergyID',0);IPS_ApplyChanges($id);
IPS_SetProperty($id,'EnergyTimeZone','Not/AZone');IPS_ApplyChanges($id);
check(snapshot($module)['energy']['month']['value']===null,'invalid timezone reported without breaking base readings');
check(count(json_decode($module->GetHistory('power',$now-86400,$now),true)['points'])===2,'power history independent of month timezone');
IPS_SetProperty($id,'EnergyTimeZone','Europe/Vienna');IPS_ApplyChanges($id);
// More than 10,000 readings must be processed page by page, not silently truncated.
$paged=IPS_CreateVariable(2);IPS_SetVariableCustomProfile($paged,'Fixture.kWh');SetValue($paged,10001.0);AC_SetLoggingStatus($archive,$paged,true);
$rows=[['TimeStamp'=>$monthStart-200001,'Value'=>0.0]];
for($i=0;$i<10001;$i++)$rows[]=['TimeStamp'=>$monthStart-12000+$i,'Value'=>(float)($i+1)];
AC_AddLoggedValues($archive,$paged,$rows);IPS_SetProperty($id,'TotalEnergyID',$paged);IPS_ApplyChanges($id);
check(snapshot($module)['energy']['month']['value']===0,'month starts at latest preceding counter reading');
$sampled=json_decode($module->GetHistory('total',$monthStart-200000,$monthStart),true);
check($sampled['points'][count($sampled['points'])-1]['value']===10001,'hourly support points preserve latest true counter');
IPS_SetProperty($id,'TotalEnergyID',$total);IPS_ApplyChanges($id);
$module->MessageSink(time(),$power,VM_UPDATE,[2600.0,true]);
check(snapshot($module)['energy']['power']['value']===2.6,'live power update refreshes energy cards');
check(!isset($GLOBALS['captured']),'electrical display never dispatches device actions');

if(!is_dir(__DIR__.'/output')) mkdir(__DIR__.'/output',0777,true);
file_put_contents(__DIR__.'/output/tile.html',$module->GetVisualizationTile());
file_put_contents(__DIR__.'/output/snapshot.json',json_encode(snapshot($module),JSON_THROW_ON_ERROR));
file_put_contents(__DIR__.'/output/history.json',$module->GetHistory('water',$start,$end));
IPS_DeleteVariable($ids['PrimaryFlowID']);$module->MessageSink(time(),$ids['PrimaryFlowID'],VM_DELETE,[]);
check(count(snapshot($module)['missing'])===1,'deleted source handled');
check(IPS_GetInstance($id)['InstanceStatus']===104,'missing source status');
echo "PASS: $checks module assertions (official SymconStubs)\n";
