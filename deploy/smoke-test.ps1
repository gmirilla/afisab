<#
.SYNOPSIS
  Black-box smoke test of a running AFISAB site. Use it on staging and again on the live site after every deploy.

.DESCRIPTION
  From the outside, like a visitor: the pages render with their CSS/JS, security headers are present, private files
  and source code are NOT reachable, anonymous visitors are sent to login, and (optionally) a real sign-in works and
  staff without two-factor are held. It changes nothing on the site except creating a session and one failed login.

.PARAMETER Base      Site address, e.g. https://invest.example.com   (no trailing slash)
.PARAMETER Email     Optional: a staff account to test sign-in with (use a throw-away account, never a real Director)
.PARAMETER Password  Its password

.EXAMPLE
  powershell -File deploy\smoke-test.ps1 -Base https://staging.example.com
  powershell -File deploy\smoke-test.ps1 -Base https://staging.example.com -Email tester@example.com -Password 'S3cret-pass'
#>
param(
    [Parameter(Mandatory)] [string] $Base,
    [string] $Email = '',
    [string] $Password = ''
)

$base = $Base.TrimEnd('/')
$jar = New-Object System.Net.CookieContainer
$results = New-Object System.Collections.ArrayList

function Record($name, $ok, $detail) { [void]$script:results.Add([pscustomobject]@{ Check = $name; Result = $(if ($ok) { 'PASS' } else { 'FAIL' }); Detail = $detail }) }

# One HTTP request with redirects OFF, so a 302 is seen as a 302. Shares a cookie jar to behave like a browser.
function Req([string]$method, [string]$path, [hashtable]$form = $null, [bool]$useJar = $false) {
    $req = [System.Net.HttpWebRequest]::Create("$base$path")
    $req.Method = $method
    $req.AllowAutoRedirect = $false
    $req.UserAgent = 'afisab-smoke'
    $req.Timeout = 30000
    if ($useJar) { $req.CookieContainer = $jar }
    if ($form) {
        $pairs = foreach ($k in $form.Keys) { [uri]::EscapeDataString($k) + '=' + [uri]::EscapeDataString([string]$form[$k]) }
        $bytes = [System.Text.Encoding]::UTF8.GetBytes(($pairs -join '&'))
        $req.ContentType = 'application/x-www-form-urlencoded'
        $req.ContentLength = $bytes.Length
        $s = $req.GetRequestStream(); $s.Write($bytes, 0, $bytes.Length); $s.Close()
    }
    try { $resp = [System.Net.HttpWebResponse]$req.GetResponse() }
    catch [System.Net.WebException] { $resp = [System.Net.HttpWebResponse]$_.Exception.Response; if (-not $resp) { throw } }
    $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
    $body = $reader.ReadToEnd(); $reader.Close()
    $out = [pscustomobject]@{ Status = [int]$resp.StatusCode; Headers = $resp.Headers; Body = $body; Location = $resp.Headers['Location'] }
    $resp.Close()
    return $out
}

# --- public pages and browser protections
$r = Req 'GET' '/'
Record 'Home page renders' (($r.Status -eq 200) -and ($r.Body -match 'Building Trust')) "status $($r.Status)"
$csp = $r.Headers['Content-Security-Policy']
Record "Content-Security-Policy present; scripts locked to 'self'" (($csp -match "script-src 'self'") -and ($csp -notmatch 'script-src[^;]*unsafe-inline')) $csp
Record 'X-Frame-Options: DENY' ($r.Headers['X-Frame-Options'] -eq 'DENY') $r.Headers['X-Frame-Options']
Record 'X-Content-Type-Options: nosniff' ($r.Headers['X-Content-Type-Options'] -eq 'nosniff') $r.Headers['X-Content-Type-Options']
Record 'Referrer-Policy set' ([bool]$r.Headers['Referrer-Policy']) $r.Headers['Referrer-Policy']
if ($base.StartsWith('https://')) {
    Record 'HSTS header present (HTTPS)' ([bool]$r.Headers['Strict-Transport-Security']) $r.Headers['Strict-Transport-Security']
    $plain = $null
    try { $plain = (New-Object System.Net.WebClient).DownloadString("http://$($base.Substring(8))/") } catch { }
    Record 'Plain http:// does not serve the site (redirects or is closed)' (-not ($plain -and $plain -match 'Building Trust')) 'http:// request did not return the home page'
}

$css = [regex]::Match($r.Body, '/build/assets/[^"''\s]+\.css').Value
$js = [regex]::Match($r.Body, '/build/assets/[^"''\s]+\.js').Value
Record 'Page links compiled CSS and JS (Vite manifest found)' (($css -ne '') -and ($js -ne '')) "$css  $js"
if ($css) { $c = Req 'GET' $css; Record 'Compiled CSS is served' (($c.Status -eq 200) -and ($c.Headers['Content-Type'] -match 'css')) "status $($c.Status)" }
if ($js)  { $c = Req 'GET' $js;  Record 'Compiled JS is served'  (($c.Status -eq 200) -and ($c.Headers['Content-Type'] -match 'javascript')) "status $($c.Status)" }

foreach ($p in '/login', '/register', '/verify', '/forgot-password', '/up') {
    $x = Req 'GET' $p
    Record "GET $p" ($x.Status -eq 200) "status $($x.Status)"
}

# --- things that must NOT be reachable
$a = Req 'GET' '/admin';               Record '/admin sends anonymous visitors to login' (($a.Status -eq 302) -and ($a.Location -match '/login')) "status $($a.Status)"
$a = Req 'GET' '/investor';            Record '/investor sends anonymous visitors to login' (($a.Status -eq 302) -and ($a.Location -match '/login')) "status $($a.Status)"
foreach ($secret in '/storage/kyc/id.jpg', '/storage/app/private/kyc/id.jpg', '/.env', '/.git/config', '/composer.json', '/composer.lock', '/artisan', '/vendor/autoload.php', '/storage/logs/laravel.log', '/database/database.sqlite') {
    $a = Req 'GET' $secret
    Record "$secret is not reachable" ($a.Status -in 403, 404) "status $($a.Status)"
}
$a = Req 'GET' '/no-such-page'
Record 'Unknown page: branded 404, nothing internal leaked' (($a.Status -eq 404) -and ($a.Body -match 'Page not found') -and ($a.Body -notmatch 'Illuminate|Stack trace|vendor/')) "status $($a.Status)"

# --- sign-in (optional)
if ($Email -ne '') {
    $login = Req 'GET' '/login' $null $true
    $token = [regex]::Match($login.Body, 'name="_token" value="([^"]+)"').Groups[1].Value
    Record 'Login form carries a CSRF token' ($token.Length -gt 20) "length $($token.Length)"

    $bad = Req 'POST' '/login' @{ _token = $token; email = $Email; password = ($Password + '-wrong') } $true
    Record 'Wrong password is refused' (($bad.Status -eq 302) -and ($bad.Location -match '/login')) "status $($bad.Status)"

    $login = Req 'GET' '/login' $null $true
    $token = [regex]::Match($login.Body, 'name="_token" value="([^"]+)"').Groups[1].Value
    $good = Req 'POST' '/login' @{ _token = $token; email = $Email; password = $Password } $true
    Record 'Correct password signs in' (($good.Status -eq 302) -and ($good.Location -notmatch '/login')) "status $($good.Status) -> $($good.Location)"

    $d = Req 'GET' '/dashboard' $null $true
    $onward = Req 'GET' ($(if ($d.Location) { ([uri]$d.Location).PathAndQuery } else { '/dashboard' })) $null $true
    Record 'After sign-in a page opens (staff without 2FA are sent to set it up)' (($onward.Status -eq 200) -or ($d.Location -match 'account/security')) "dashboard -> $($d.Status) $($d.Location)"
    $ck = $jar.GetCookies([uri]$base)
    Record 'Session cookie is HttpOnly' (($ck | Where-Object { $_.Name -match 'session' -and $_.HttpOnly }).Count -ge 1) (($ck | ForEach-Object { "$($_.Name) httponly=$($_.HttpOnly) secure=$($_.Secure)" }) -join '; ')
    if ($base.StartsWith('https://')) {
        Record 'Session cookie is Secure (HTTPS only)' (($ck | Where-Object { $_.Name -match 'session' -and $_.Secure }).Count -ge 1) 'SESSION_SECURE_COOKIE'
    }
}

$results | Format-Table -AutoSize -Wrap | Out-String -Width 220
$fails = @($results | Where-Object { $_.Result -eq 'FAIL' }).Count
"$($results.Count) checks, $fails failed."
exit $(if ($fails) { 1 } else { 0 })
