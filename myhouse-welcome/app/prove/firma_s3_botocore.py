import sys, json, hashlib
from botocore.auth import S3SigV4Auth, S3SigV4QueryAuth
from botocore.awsrequest import AWSRequest
from botocore.credentials import Credentials
import datetime
from unittest import mock

casi = json.load(open(sys.argv[1]))
out = []
for c in casi:
    cred = Credentials(c['key'], c['secret'], c.get('token') or None)
    fisso = datetime.datetime.strptime(c['amz'], '%Y%m%dT%H%M%SZ')
    with mock.patch('botocore.auth.get_current_datetime', return_value=fisso):
        if c['kind'] == 'headers':
            req = AWSRequest(method=c['method'], url=c['url'], data=c['body'].encode(), headers=c['headers'])
            req.context['payload_signing_enabled'] = True
            S3SigV4Auth(cred, 's3', c['region']).add_auth(req)
            out.append(req.headers['Authorization'])
        else:
            req = AWSRequest(method=c['method'], url=c['url'])
            S3SigV4QueryAuth(cred, 's3', c['region'], expires=c['expires']).add_auth(req)
            out.append(req.url)
print(json.dumps(out))
