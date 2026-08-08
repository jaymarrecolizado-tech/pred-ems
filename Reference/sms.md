Paste this whole section into your HRMIS chat as the SMS provider contract.



Provider (not a SaaS SMS broker)







Item



Value





Product



SMS Gateway for Android (capcom6)





Docs / site



https://sms-gate.app — https://docs.sms-gate.app





GitHub



https://github.com/capcom6/android-sms-gateway





How SMS is sent



Android phone with SIM → gateway HTTP API → message goes out over the SIM





LOKA reference client



SmsGateway in LOKA (public_html/classes/SmsGateway.php) — compatible client, not a LOKA-only API

LOKA does not expose a public “LOKA SMS API” for other apps. HRMIS should call the same gateway LOKA already uses (shared phone/server), with its own queue/settings.



Modes (pick one; match LOKA System Control → SMS)







Mode



Gateway base URL



API path (append to base)



Full send URL example





A — Local phone (dev/LAN)



http://PHONE_LAN_IP:8080



/message



http://192.168.x.x:8080/message





B — Private / self-hosted



https://sms.yourdomain.com



/api/3rdparty/v1/messages



https://sms.yourdomain.com/api/3rdparty/v1/messages





C — Public cloud



https://api.sms-gate.app



/3rdparty/v1/messages



https://api.sms-gate.app/3rdparty/v1/messages





Always use HTTPS for cloud (api.sms-gate.app). HTTP can 308-redirect and break POST.



Base URL is origin only (no /3rdparty/... in the base). Path is separate.



Cloud: do not use /api/3rdparty/... — use /3rdparty/v1/messages.



Auth





HTTP Basic Auth



Header effectively: Authorization: Basic base64(username:password)



Username / password: from the Android SMS Gateway app (Local Server screen, or after device registers to private/cloud).



Same credentials can be reused by LOKA and HRMIS if both point at the same gateway instance.



Send SMS — HTTP contract (exact LOKA payload)

POST {baseUrl}{apiPath}
Content-Type: application/json
Accept: application/json
Authorization: Basic {base64(user:pass)}

JSON body:

{
  "textMessage": {
    "text": "Your SMS body here"
  },
  "phoneNumbers": [
    "+639171234567"
  ]
}

Rules LOKA follows (HRMIS should match):





Phones in E.164 (e.g. +63… for PH). LOKA default country code setting: 63.



Success = HTTP 2xx.



Gateway may return JSON with message id in id or messageId — store if you have a log table.



Timeouts: connect ~3–5s, total read ~15s (avoid blocking web requests; use a queue).



Soft-fail: business actions must succeed even if SMS fails.

cURL example (cloud):

curl -u 'USERNAME:PASSWORD' \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -X POST 'https://api.sms-gate.app/3rdparty/v1/messages' \
  -d '{"textMessage":{"text":"HRMIS test"},"phoneNumbers":["+639171234567"]}'

PHP sketch (mirrors LOKA):

$ch = curl_init('https://api.sms-gate.app/3rdparty/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'textMessage' => ['text' => $message],
        'phoneNumbers' => [$phoneE164],
    ], JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_USERPWD => $username . ':' . $password,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_FOLLOWLOCATION => false,
]);



Health check (optional)







Mode



Probe





Local / private



GET {baseUrl}/health — expect 2xx





Cloud



HEAD/GET https://api.sms-gate.app/ — any HTTP response means reachable (no /health on cloud)



Suggested HRMIS env vars (same meaning as LOKA)

SMS_ENABLED=false
SMS_GATEWAY_URL=https://api.sms-gate.app
SMS_GATEWAY_USERNAME=
SMS_GATEWAY_PASSWORD=
SMS_API_PATH=/3rdparty/v1/messages
SMS_DEFAULT_COUNTRY_CODE=63
SMS_TIMEOUT_SECONDS=15
SMS_MAX_MESSAGE_LENGTH=320

Local test instead: SMS_GATEWAY_URL=http://PHONE_IP:8080 and SMS_API_PATH=/message.



Architecture to copy from LOKA (recommended)

flowchart LR
  AppEvent[HRMIS_event] --> Queue[sms_queue]
  Queue --> Worker[cron_or_worker]
  Worker --> Gateway[sms_gate_API]
  Gateway --> Phone[Android_SIM]





Enqueue on notify; send asynchronously (cron/worker), same pattern as LOKA email/SMS queues.



Do not send synchronously inside every HTTP request if the gateway is slow/offline.