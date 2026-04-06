<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; margin: 0; background-color: #f8fafc; }
        .card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 90%; }
        .icon { font-size: 64px; color: #10b981; margin-bottom: 16px; }
        h1 { color: #0f172a; margin: 0 0 8px 0; font-size: 24px; }
        p { color: #64748b; margin: 0 0 24px 0; line-height: 1.5; }
        .btn { background: #2563eb; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 16px; text-decoration: none; display: inline-block; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✨</div>
        <h1>Payment Successful!</h1>
        <p>Your appointment is confirmed. You can now close this browser and return to the Azure Dental app.</p>
        <button class="btn" onclick="window.close()">Close</button>
    </div>
</body>
</html>
