import sys, json, datetime
from unittest import mock
from botocore.auth import SigV4Auth
from botocore.awsrequest import AWSRequest
from botocore.credentials import Credentials

casi = json.load(open(sys.argv[1]))
out = []
for c in casi:
    cred = Credentials(c['key'], c['secret'], c.get('token') or None)
    fisso = datetime.datetime.strptime(c['amz'], '%Y%m%dT%H%M%SZ')
    with mock.patch('botocore.auth.get_current_datetime', return_value=fisso):
        req = AWSRequest(method='POST', url=c['url'], data=c['body'].encode(), headers=c['headers'])
        SigV4Auth(cred, 'translate', c['region']).add_auth(req)
        out.append(req.headers['Authorization'])
print(json.dumps(out))
