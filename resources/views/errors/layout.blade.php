<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · AFISAB</title>
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #f9fafb; color: #1f2937; margin: 0; }
        header { background: #08532a; color: #fff; padding: 14px 20px; font-weight: 900; letter-spacing: .04em; }
        header span { color: #c9a227; }
        main { max-width: 34rem; margin: 12vh auto; padding: 0 20px; text-align: center; }
        .code { font-size: 4rem; font-weight: 900; color: #08532a; margin: 0; }
        h1 { margin: .2rem 0 .6rem; font-size: 1.4rem; }
        p { color: #4b5563; line-height: 1.5; }
        a { display: inline-block; margin-top: 1rem; background: #08532a; color: #fff; padding: .6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
<header>AFISAB <span>NIG. LTD</span></header>
<main>
    <p class="code">@yield('code')</p>
    <h1>@yield('heading')</h1>
    <p>@yield('message')</p>
    <a href="/">Back to the home page</a>
</main>
</body>
</html>
