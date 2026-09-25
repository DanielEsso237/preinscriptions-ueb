"""Parcours navigateur réels. Fixtures : access-integration.php --fixtures.
Les identifiants restent dans /tmp ; aucun compte existant n'est utilisé.
"""
import json
import os
import base64
import io
import secrets
import zipfile
from pathlib import Path
from playwright.sync_api import sync_playwright

if Path('/usr/local/nodejs/bin/node').exists():
    os.environ.setdefault('PLAYWRIGHT_NODEJS_PATH', '/usr/local/nodejs/bin/node')
# Le paquet système peut être incomplet ; réutiliser le pilote déjà installé.
if not Path('/usr/share/nodejs/playwright/cli.js').exists():
    cached_drivers = list(Path.home().glob('.cache/ms-playwright-go/*/package/cli.js'))
    if cached_drivers:
        import playwright._impl._transport as transport
        driver = sorted(cached_drivers)[-1]
        transport.compute_driver_executable = lambda: (str(driver.parent.parent / 'node'), str(driver))

FIXTURE_PATH = Path('/tmp/ueb-access-fixtures.json')
fixtures = json.loads(FIXTURE_PATH.read_text())
base = fixtures['base']
errors = []

def check(condition, label):
    if not condition:
        raise AssertionError(label)
    print('OK : ' + label, flush=True)

def login(browser, kind, width=1440):
    context = browser.new_context(viewport={'width': width, 'height': 1050}, locale='fr-FR')
    page = context.new_page()
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto(base + '?ueb_portal=login')
    user = fixtures['users'][kind]
    page.locator('input[name=login]').fill(user['login'])
    page.locator('input[name=password]').fill(user['password'])
    page.get_by_role('button', name='Accéder à mon espace').click()
    page.wait_for_url(lambda url: 'ueb_portal=login' not in str(url))
    return context, page

def api(page, action, data=None, nonce='dataNonce'):
    return page.evaluate('''async ({action,data,nonce}) => {
        const body = new URLSearchParams({action, nonce:uebPortal[nonce], ...data});
        const response = await fetch(uebPortal.ajax, {method:'POST',body});
        return {status:response.status, text:await response.text()};
    }''', {'action':action, 'data':data or {}, 'nonce':nonce})

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', headless=True, args=['--no-sandbox'])
    anonymous = browser.new_context(viewport={'width':1440,'height':1000})
    page = anonymous.new_page()
    page.goto(base + '?ueb_portal=login')
    page.screenshot(path='/tmp/ueb-login-desktop.png', full_page=True)
    check(page.locator('input[name=password]').get_attribute('autocomplete') == 'current-password', 'Login compatible avec les gestionnaires de mots de passe')
    page.set_viewport_size({'width':375,'height':812})
    page.screenshot(path='/tmp/ueb-login-mobile.png', full_page=True)
    check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Connexion mobile sans débordement')
    page.goto(base + '?ueb_portal=forgot')
    check(page.get_by_role('button', name='Recevoir le lien').count() == 1, 'Mot de passe oublié dans le front')
    anonymous.close()

    context, page = login(browser, 'single')
    page.locator('#portal-dashboard[aria-busy=false]').wait_for()
    page.locator('#portal-list[aria-busy=false]').wait_for()
    check('establishment' in page.url, 'Redirection portée unique vers son établissement')
    check(page.locator('.portal-kpi').first.inner_text().find('2') >= 0, 'KPI limité aux dossiers temporaires autorisés')
    check(page.locator('.portal-table tbody tr').count() >= 2, 'Liste de préinscrits affichée')
    check(page.get_by_role('link', name='Rôles & accès').count() == 0, 'Menu de gestion masqué sans permission')
    page.screenshot(path='/tmp/ueb-establishment-desktop.png', full_page=True)
    foreign = fixtures['establishments'][1]
    result = api(page, 'ueb_admin_get_dossiers', {'faculte': foreign})
    check(result['status'] == 403, 'API liste : établissement forgé refusé')
    result = api(page, 'ueb_admin_get_dossier_detail', {'numero_dossier':fixtures['dossiers'][2]})
    check(not json.loads(result['text'])['success'], 'API détail : dossier étranger refusé')
    result = api(page, 'ueb_portal_stats', {'view':'overview'}, nonce='nonce')
    check(result['status'] == 403, 'API vue globale interdite sans capability')
    result = api(page, 'ueb_portal_stats', {'view':'establishment','establishment':foreign}, nonce='nonce')
    check(result['status'] == 403, 'API stats : portée appliquée')
    result = api(page, 'ueb_admin_export_csv')
    check(result['status'] == 200 and fixtures['dossiers'][0] in result['text'] and fixtures['dossiers'][2] not in result['text'], 'Export CSV isolé')
    for export_format, extension in [('pdf','pdf'),('excel','xlsx'),('word','docx')]:
        result = page.evaluate('''async format => {
            const response = await fetch(uebPortal.ajax, {method:'POST',body:new URLSearchParams({action:'ueb_admin_export',format,nonce:uebPortal.dataNonce})});
            const bytes = new Uint8Array(await response.arrayBuffer());
            let binary = ''; bytes.forEach(byte => binary += String.fromCharCode(byte));
            return {status:response.status, data:btoa(binary)};
        }''', export_format)
        binary = base64.b64decode(result['data'])
        check(result['status'] == 200 and binary.startswith(b'%PDF' if extension == 'pdf' else b'PK'), 'Export valide : ' + extension)
        if extension != 'pdf':
            archive = zipfile.ZipFile(io.BytesIO(binary))
            xml = '\n'.join(archive.read(name).decode('utf-8') for name in archive.namelist() if name.endswith('.xml'))
            check(fixtures['dossiers'][0] in xml and fixtures['dossiers'][2] not in xml, 'Dossiers isolés dans ' + extension)
    result = api(page, 'ueb_admin_get_dossiers', {'nonce':'invalide'})
    check(result['status'] == 403, 'Nonce invalide refusé')
    response = page.goto(base + '?ueb_portal=establishment&establishment=' + str(foreign))
    check(response.status == 403, 'URL détail hors portée refusée')
    page.goto(base + 'wp-admin/')
    check('wp-admin' not in page.url, 'wp-admin redirigé pour le rôle métier')
    page.locator('#portal-dashboard[aria-busy=false]').wait_for()
    check(page.locator('#wpadminbar').count() == 0, 'Admin bar absente')
    for width in [375,768,1024]:
        page.set_viewport_size({'width':width,'height':1000})
        page.wait_for_timeout(400)
        if page.evaluate('document.documentElement.scrollWidth > innerWidth'):
            page.screenshot(path='/tmp/ueb-overflow.png',full_page=True)
            print(page.evaluate('''Array.from(document.querySelectorAll('body *')).filter(el => el.getBoundingClientRect().right > innerWidth + 2 && getComputedStyle(el).position !== 'absolute').map(el => ({tag:el.tagName,cls:el.className,w:el.getBoundingClientRect().width})).slice(0,12)'''))
        check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), f'Dashboard sans débordement à {width}px')
    page.set_viewport_size({'width':375,'height':812})
    page.screenshot(path='/tmp/ueb-establishment-mobile.png',full_page=True)
    page.emulate_media(reduced_motion='reduce')
    check(page.locator('.portal-kpi').first.evaluate('(el)=>getComputedStyle(el).animationName') == 'none', 'Animations désactivées en mouvement réduit')
    page.get_by_role('link',name='Référentiels',exact=True).click()
    page.locator('#admin-ref-table-wrap tbody tr').first.wait_for()
    check(page.locator('#admin-ref-nav [data-ref]').count() == 1, 'Une seule rubrique de référence autorisée')
    check(page.locator('#admin-ref-table-wrap tbody tr').count() == 1, 'Référentiel limité à la filière autorisée')
    response = page.evaluate('''async foreign => {
        const body = new URLSearchParams({action:'ueb_admin_ref_get',ref_key:'filieres',id:foreign,nonce:uebAdminReferences.nonce});
        return (await (await fetch(uebAdminReferences.ajax_url,{method:'POST',body})).json()).success;
    }''', fixtures['filieres'][1])
    check(not response, 'API référentiels : objet étranger refusé')
    page.get_by_role('link',name='Mon espace',exact=True).click()
    admin_link = page.get_by_role('link', name='Administration détaillée')
    if admin_link.count():
        admin_link.click()
        page.wait_for_timeout(1200)
        check(page.locator('#admin-tabbtn-stats').count() == 1, 'Ancien admin conservé')
        check(page.locator('#admin-liste-container tbody tr').count() == 2, 'Ancienne liste isolée')
    context.close()

    context, page = login(browser, 'limited')
    page.locator('#portal-dashboard[aria-busy=false]').wait_for()
    check(page.locator('#portal-students').count() == 0, 'Liste absente sans permission')
    check(page.locator('#portal-evolution').count() == 0, 'Courbe absente sans permission')
    for action in ['ueb_admin_get_dossiers','ueb_admin_get_dossier_detail','ueb_admin_export','ueb_admin_export_csv','ueb_admin_get_effectifs','ueb_admin_get_stats']:
        check(api(page, action)['status'] == 403, 'Capability exigée : ' + action)
    check(api(page, 'ueb_access_mutate', {'operation':'role_save'}, nonce='nonce')['status'] == 403, 'Gestion de rôles interdite par API')
    context.close()

    context, page = login(browser, 'multiple')
    page.locator('#portal-dashboard[aria-busy=false]').wait_for()
    check(page.locator('#portal-establishment option').count() == 2, 'Sélecteur limité aux deux établissements autorisés')
    page.locator('#portal-establishment').select_option(str(foreign))
    page.get_by_role('button', name='Afficher', exact=True).click()
    page.locator('#portal-dashboard[aria-busy=false]').wait_for()
    check(page.locator('.portal-header h1').inner_text() == 'Établissement test B', 'Navigation vers le deuxième établissement')
    context.close()

    context, page = login(browser, 'director')
    page.locator('#portal-dashboard[aria-busy=false]').wait_for()
    check(page.locator('.portal-est-card').count() >= 3, 'Vue globale : une carte par établissement')
    page.screenshot(path='/tmp/ueb-overview-desktop.png',full_page=True)
    page.get_by_role('link', name='Rôles & accès').click()
    # Reprise d'un essai interrompu : seulement nos rôles temporaires nommés.
    existing = json.loads(page.locator('#portal-role-data').text_content())['roles']
    for role in existing:
        if role['name'].startswith('Rôle temporaire navigateur'):
            api(page, 'ueb_access_mutate', {'operation':'role_delete','key':role['key'],'revision':role['revision'],'confirmed':1}, nonce='nonce')
    page.reload()
    page.get_by_role('button',name='Créer un rôle').click()
    page.locator('#portal-role-form input[name=name]').fill('Rôle temporaire navigateur')
    page.locator('#role-next').click()
    page.locator(f'#portal-role-form input[name="establishments[]"][value="{fixtures["establishments"][0]}"]').check()
    page.locator('#role-next').click()
    page.locator('#role-preset').select_option('0')
    check('statistiques' in page.locator('#role-preview').inner_text(), 'Aperçu du rôle mis à jour en direct')
    page.screenshot(path='/tmp/ueb-role-wizard.png', full_page=True)
    with page.expect_navigation(wait_until='networkidle'):
        page.locator('#role-submit').click()
    role = next(role for role in json.loads(page.locator('#portal-role-data').text_content())['roles'] if role['name'] == 'Rôle temporaire navigateur')
    check(bool(role['key']), 'Création d’un rôle arbitraire depuis l’assistant')
    fixtures['roles'].append(role['key']); FIXTURE_PATH.write_text(json.dumps(fixtures))
    page.locator('.portal-role-card',has=page.get_by_role('heading',name='Rôle temporaire navigateur',exact=True)).get_by_role('button',name='Modifier',exact=True).click()
    page.locator('#portal-role-form input[name=name]').fill('Rôle temporaire navigateur modifié')
    page.locator('#role-next').click(); page.locator('#role-next').click()
    with page.expect_navigation(wait_until='networkidle'):
        page.locator('#role-submit').click()
    check(page.get_by_role('heading',name='Rôle temporaire navigateur modifié',exact=True).count() == 1, 'Modification du rôle persistée')
    role_key = role['key']
    page.get_by_role('link',name='Comptes utilisateurs',exact=True).click()
    user_login = 'ueb_ui_' + secrets.token_hex(4)
    new_password = secrets.token_urlsafe(24)
    for name, value in [('name','Compte temporaire navigateur'),('login',user_login),('email',user_login + '@example.invalid'),('password',new_password)]:
        page.locator('#portal-user-form input[name=' + name + ']').fill(value)
    page.locator('#portal-user-form select[name=role]').select_option(role_key)
    with page.expect_navigation(wait_until='networkidle'):
        page.locator('#portal-user-form button[type=submit]').click()
    button = page.locator('[data-user-name="Compte temporaire navigateur"]').last
    user_id = int(button.get_attribute('data-assign-user'))
    fixtures['users'][user_login] = {'id':user_id,'login':user_login,'password':new_password,'role':role_key}
    FIXTURE_PATH.write_text(json.dumps(fixtures))
    check(user_id > 0, 'Compte créé depuis le front')
    button.click()
    page.locator('#portal-user-form select[name=role]').select_option(fixtures['users']['limited']['role'])
    with page.expect_navigation(wait_until='networkidle'):
        page.locator('#portal-user-form button[type=submit]').click()
    row = page.locator('tr',has=page.locator('[data-assign-user="' + str(user_id) + '"]'))
    check('limited' in row.inner_text(), 'Compte réaffecté depuis le front')
    page.get_by_role('link',name='Rôles & accès',exact=True).click()
    page.locator('[data-role-copy="' + role_key + '"]').click()
    page.locator('#role-next').click(); page.locator('#role-next').click()
    with page.expect_navigation(wait_until='networkidle'):
        page.locator('#role-submit').click()
    copied = next(item for item in json.loads(page.locator('#portal-role-data').text_content())['roles'] if item['name'].endswith('modifié — copie'))
    fixtures['roles'].append(copied['key']); FIXTURE_PATH.write_text(json.dumps(fixtures))
    check(copied['key'] != role_key, 'Duplication avec identifiant indépendant')
    page.locator('[data-role-delete="' + copied['key'] + '"]').click()
    page.locator('#portal-delete-form input[name=confirmed]').check()
    with page.expect_navigation(wait_until='networkidle'):
        page.locator('#portal-delete-form button[type=submit]').click()
    check(page.locator('[data-role-delete="' + copied['key'] + '"]').count() == 0, 'Suppression confirmée dans l’interface')
    page.get_by_role('link',name='Gérer les établissements').click()
    page.locator(f'[data-edit-establishment="{fixtures["establishments"][2]}"]').click()
    page.locator('#portal-establishment-form select[name=actif]').select_option('0')
    with page.expect_navigation(wait_until='networkidle'):
        page.locator('#portal-establishment-form button[type=submit]').click()
    check(page.get_by_text('Inactif',exact=True).count() >= 1, 'Désactivation d’un établissement depuis le front')
    page.get_by_role('link',name='Déconnexion',exact=False).click()
    check('ueb_portal=login' in page.url, 'Déconnexion front')
    context.close()
    check(not errors, 'Aucune erreur JavaScript : ' + '; '.join(errors))
    browser.close()
