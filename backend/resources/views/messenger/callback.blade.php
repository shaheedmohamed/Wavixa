<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messenger Connect</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f0f2f5;
            color: #1c1e21;
            text-align: center;
            padding: 20px;
        }
        .box {
            background: #fff;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            max-width: 420px;
            width: 100%;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e4e6eb;
            border-top-color: #1877f2;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="box">
        <div class="spinner"></div>
        <h3>جاري إغلاق النافذة...</h3>
    </div>
    <script>
        (function () {
            const payload = {!! $payload !!};
            const allowedOrigin = '{{ rtrim(config('app.url'), '/') }}';
            if (window.opener) {
                window.opener.postMessage({ type: 'messenger-oauth', payload: payload }, allowedOrigin);
            }
            setTimeout(function () {
                window.close();
            }, 500);
        })();
    </script>
</body>
</html>
