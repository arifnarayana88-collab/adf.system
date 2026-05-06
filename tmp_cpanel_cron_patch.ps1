$cpHost = 'guangmao.iixcp.rumahweb.net'
$user = 'adfb2574'
$pass = '@Nnoc2026'
$cmd = "/usr/bin/perl -0pi -e 's/\\$stmt->execute\\(\\[\\$today\\]\\);/\\$stmt->execute([\\$today]);\\n    \\$inHouseGuests = \\$stmt->fetchAll(PDO::FETCH_ASSOC);/' /home/adfb2574/public_html/modules/frontdesk/breakfast.php"
$url = 'https://' + $cpHost + ':2083/json-api/cpanel?cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=Cron&cpanel_jsonapi_func=add_line&minute=*&hour=*&day=*&month=*&weekday=*&command=' + [uri]::EscapeDataString($cmd)

Write-Host $url
& curl.exe -k -u ($user + ':' + $pass) $url