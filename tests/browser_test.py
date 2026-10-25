"""Real browser, synthetic module fixtures and a simulated Symcon HTML SDK."""
import copy
import json
import os
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT = Path(__file__).resolve().parents[1]
html = (ROOT/'tests/output/tile.html').read_text()
snapshot = json.loads((ROOT/'tests/output/snapshot.json').read_text())
checks = 0
def check(condition, message):
    global checks
    assert condition, message
    checks += 1
with sync_playwright() as p:
    options = {'headless': True}
    if os.environ.get('CHROMIUM_PATH'):
        options['executable_path'] = os.environ['CHROMIUM_PATH']
    browser = p.chromium.launch(**options)
    page = browser.new_page(viewport={'width':1200,'height':1100},device_scale_factor=1)
    errors=[]
    page.on('pageerror',lambda error:errors.append(str(error)))
    page.set_content(html)
    check(page.locator('#modes button').first.is_disabled(),'read only outside SDK')
    check(page.locator('#water').inner_text()=='45,6 °C','actual water fixture')
    check(page.locator('#roomTarget').inner_text()=='22 °C','room target explicitly shown')
    page.evaluate("() => { window.calls=[]; window.requestAction=(ident,value)=>{calls.push({ident,value});}; }")
    page.wait_for_function("!document.querySelector('#modes button').disabled")
    page.locator('#modes button').nth(1).click()
    check(page.evaluate('calls')==[{'ident':'ChangeMode','value':'water'}],'typed mode request')
    check(page.locator('#modeBadge').inner_text()=='Heizen + Warmwasser','no optimistic change')
    check(page.locator('#waterTargetInput').is_disabled(),'pending prevents concurrent action')
    confirmed=copy.deepcopy(snapshot)
    confirmed['values']['mode'].update(value='water',formatted='Nur Warmwasser')
    page.evaluate('handleMessage',json.dumps(confirmed))
    check(page.locator('#modeBadge').inner_text()=='Nur Warmwasser','acknowledged source displayed')
    page.locator('#waterTargetInput').fill('51')
    page.locator('#waterTargetForm button').click()
    check(page.evaluate('calls.at(-1)')=={'ident':'SetWaterTarget','value':51},'water target request')
    check(page.locator('#waterTarget').inner_text()=='50 °C','target stays confirmed source')
    confirmed['values']['waterTarget']['value']=51
    page.evaluate('handleMessage',confirmed)
    check(page.locator('#waterTargetInput').is_enabled(),'target acknowledged')
    page.locator('#roomTargetInput').fill('22.5')
    page.locator('#roomTargetForm button').click()
    check(page.evaluate('calls.at(-1)')=={'ident':'SetRoomTarget','value':22.5},'room target request')
    confirmed['actionError']='Testfehler der Variablenaktion'
    page.evaluate('handleMessage',confirmed)
    check('Testfehler' in page.locator('#feedback').inner_text(),'server error visible')
    confirmed['actionError']=''
    page.evaluate('handleMessage',confirmed)
    page.locator('#roomTargetInput').fill('999')
    count=len(page.evaluate('calls'))
    page.locator('#roomTargetForm button').click()
    check(len(page.evaluate('calls'))==count,'browser range validation')
    page.locator('#roomTargetInput').fill('23')
    page.clock.install()
    page.locator('#roomTargetForm button').click()
    page.clock.fast_forward(16000)
    check('Keine Bestätigung' in page.locator('#feedback').inner_text(),'unconfirmed command timeout')
    check(page.locator('#roomTargetInput').is_enabled(),'timeout restores controls')
    page.evaluate("() => { stopPending(''); render(INITIAL); }")
    page.locator('#roomTargetInput').fill('22')
    page.locator('#waterTargetInput').fill('50')
    page.locator('h1').click()
    # Keep screenshot explicitly representative, with synthetic values from tests.
    page.screenshot(path=str(ROOT/'docs/dashboard.png'),full_page=True)
    for width in [320,390,768,1200]:
        page.set_viewport_size({'width':width,'height':1000})
        check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'),'no horizontal overflow '+str(width))
        check(page.locator('#waterTargetForm button').is_visible(),'controls reachable '+str(width))
    malicious=copy.deepcopy(snapshot)
    malicious['mode']['choices'][0]['label']='<img src=x onerror="window.injected=true">'
    page.evaluate('handleMessage',malicious)
    check(page.locator('#modes img').count()==0,'captions are text only')
    unavailable=copy.deepcopy(snapshot)
    unavailable['missing']=['Warmwasser Ist'];unavailable['values']['water'].update(value=None,formatted='—')
    unavailable['controls']['waterTarget'].update(writable=False,reason='Keine Aktion')
    page.evaluate('handleMessage',unavailable)
    check(page.locator('#water').inner_text()=='—','missing source never displayed as zero')
    check(page.locator('#waterTargetInput').is_disabled(),'missing action is read only')
    check(not errors,'no JavaScript errors: '+str(errors))
    browser.close()
print(f'PASS: {checks} browser assertions')
