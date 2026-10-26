{{-- Shown by the installer when the front-end hasn't been built yet, so it can't use @vite. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Install PickleBall</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; background: #0a1017; color: #0a1017; font: 16px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { max-width: 560px; background: #fff; border-radius: 16px; padding: 32px; }
        h1 { margin: 0 0 8px; font-size: 26px; line-height: 1.25; }
        p { margin: 0 0 16px; color: #334155; }
        pre { margin: 0 0 16px; padding: 14px 16px; overflow-x: auto; background: #0a1017; color: #f9d35c; border-radius: 10px; font-size: 15px; }
        code { font-family: ui-monospace, Consolas, monospace; }
        a { display: inline-block; margin-top: 8px; padding: 12px 20px; border-radius: 10px; background: #eeb008; color: #0a1017; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
    <main>
        <h1>One more step before installing</h1>
        <p>The site’s styles and scripts haven’t been built yet, so the installer can’t start. From the project folder, run:</p>
        <pre><code>npm install
npm run build</code></pre>
        <p>Deploying to shared hosting without Node? Run those two commands on your computer first, then upload the <code>public_html/build</code> folder with the rest of the site.</p>
        <a href="{{ url('/install') }}">I’ve done that, try again</a>
    </main>
</body>
</html>
