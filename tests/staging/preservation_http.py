#!/usr/bin/env python3
"""Opt-in HTTP acceptance against synthetic accounts created by preservation_r2.php."""
import argparse
import hashlib
import html
import http.cookiejar
import io
import json
import re
import urllib.error
import urllib.parse
import urllib.request
import zipfile
from pathlib import Path


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Browser:
    def __init__(self, url, credentials):
        self.url = url.rstrip('/')
        self.cookies = http.cookiejar.CookieJar()
        self.client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies), NoRedirect())
        status, _, markup = self.request('/login')
        assert status == 200, 'Login page unavailable'
        self.csrf = re.search(rb'<meta name="csrf-token" content="([^"]+)"', markup).group(1).decode()
        status, headers, _ = self.request('/login', credentials)
        assert status in (302, 303) and '/login' not in headers.get('Location', ''), 'Synthetic login failed'
        self.refresh()

    def refresh(self):
        status, _, markup = self.request('/accounting/conservation')
        assert status == 200, 'Conservation unavailable after login'
        self.csrf = re.search(rb'<meta name="csrf-token" content="([^"]+)"', markup).group(1).decode()
        script = re.search(rb'<script data-page="[^"]+" type="application/json">(.*?)</script>', markup, re.S)
        page = script.group(1).decode() if script else html.unescape(re.search(rb'data-page="([^"]+)"', markup).group(1).decode())
        self.version = json.loads(page)['version']

    def request(self, path, data=None, inertia=False):
        headers = {'Accept': 'text/html', 'Origin': self.url, 'Referer': self.url + '/accounting/conservation'}
        if inertia:
            headers['X-Inertia'] = 'true'
            headers['X-Inertia-Version'] = self.version
        body = None if data is None else urllib.parse.urlencode({**data, '_token': self.csrf}).encode()
        req = urllib.request.Request(self.url + path, data=body, headers=headers)
        try:
            response = self.client.open(req, timeout=45)
        except urllib.error.HTTPError as response_error:
            response = response_error
        with response:
            return response.status, response.headers, response.read()

    def page(self):
        status, _, body = self.request('/accounting/conservation', inertia=True)
        assert status == 200, 'Conservation page failed'
        page = json.loads(body)
        assert page['component'] == 'Accounting/Archives/Packages'
        return page['props']


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--url', required=True)
    parser.add_argument('--credentials', type=Path, required=True)
    parser.add_argument('--business-report', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    assert urllib.parse.urlparse(args.url).hostname in ('127.0.0.1', 'localhost'), 'Use only an isolated loopback QA application'
    assert args.credentials.stat().st_mode & 0o077 == 0, 'Private credentials required'
    assert not args.output.exists(), 'Output must be new'
    fixture = json.loads(args.business_report.read_text())
    assert fixture['status'] == 'passed'
    credentials = json.loads(args.credentials.read_text())
    result = {'schema': 1, 'kind': 'r2-isolated-http-acceptance', 'status': 'failed', 'run_id': fixture['run_id'], 'edition': fixture.get('edition'), 'checks': {}}
    stage = 'owner-login'
    try:
        owner = Browser(args.url, credentials['owner'])
        result['checks']['owner_login'] = True
        result['companies'] = []
        for company in fixture['companies']:
            stage = 'owner-company-switch'
            status, headers, _ = owner.request('/organizations/' + company['organization_id'] + '/switch', {})
            assert status in (302, 303) and '/billing' not in headers.get('Location', ''), 'Company switch blocked'
            owner.refresh()
            props = owner.page()
            rows = props['packages']['data']
            assert len(rows) == 1 and rows[0]['id'] == company['package_id'], 'Company page exposed other packages'
            assert rows[0]['from'] == '2026-07-08' and rows[0]['to'] == '2027-12-31'
            assert rows[0]['state'] == 'prepared' and rows[0]['archival_pdf'] is False
            assert props['canManage'] is True
            stage = 'owner-export'
            status, _, body = owner.request(company['export_route'])
            assert status == 200
            with zipfile.ZipFile(io.BytesIO(body)) as archive:
                manifest_hash = hashlib.sha256(archive.read('manifest.json')).hexdigest()
                assert manifest_hash == company['manifest_sha256'], 'HTTP export changed captured manifest'
                manifest = json.loads(archive.read('manifest.json'))
                assert manifest['proof_profile'] == 'gaeld-content-package/1.0.0'
                for entry in manifest['files']:
                    assert hashlib.sha256(archive.read(entry['path'])).hexdigest() == entry['sha256']
            result['companies'].append({'organization_id': company['organization_id'], 'package_id': company['package_id'], 'manifest_sha256': manifest_hash, 'export_sha256': hashlib.sha256(body).hexdigest()})
        result['checks'].update({'owner_three_company_pages': True, 'owner_three_exact_exports': True, 'content_only_profile_and_dates': True})
        stage = 'viewer-login'
        viewer = Browser(args.url, credentials['viewer'])
        props = viewer.page()
        own, foreign = fixture['companies'][:2]
        assert props['canManage'] is False and [row['id'] for row in props['packages']['data']] == [own['package_id']]
        assert viewer.request('/accounting/conservation', {'fiscal_year_id': own['fiscal_year_id']})[0] == 403
        assert viewer.request('/accounting/conservation/' + own['package_id'] + '/resume', {})[0] == 403
        stage = 'viewer-foreign-package'
        assert viewer.request(foreign['export_route'])[0] == 404
        for action in ('verify', 'resume'):
            assert viewer.request('/accounting/conservation/' + foreign['package_id'] + '/' + action, {})[0] == 404
        assert viewer.request('/organizations/' + foreign['organization_id'] + '/switch', {})[0] == 404
        status, _, body = viewer.request(own['export_route'])
        assert status == 200
        with zipfile.ZipFile(io.BytesIO(body)) as archive:
            assert hashlib.sha256(archive.read('manifest.json')).hexdigest() == own['manifest_sha256']
        result['checks'].update({'viewer_login_and_own_export': True, 'viewer_cannot_prepare_or_resume': True, 'foreign_company_actions_hidden': True})
        result['status'] = 'passed'
    except Exception as exc:
        result.update({'failed_stage': stage, 'error_class': type(exc).__name__})
        if isinstance(exc, AssertionError):
            result['error'] = str(exc)
    with args.output.open('x') as stream:
        json.dump(result, stream, indent=2)
        stream.write('\n')
    print(json.dumps({'status': result['status'], 'stage': result.get('failed_stage', 'complete')}))
    raise SystemExit(0 if result['status'] == 'passed' else 1)


if __name__ == '__main__':
    main()
