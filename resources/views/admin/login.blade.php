
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendo | دخول الإدارة</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f4f5f7;
            font-family: Arial, sans-serif;
            color: #202938;
        }
        .login-card {
            width: min(420px, 92%);
            padding: 36px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 35px #00000012;
        }
        h1 { margin: 0 0 8px; text-align: center; }
        .subtitle {
            text-align: center;
            color: #788292;
            margin-bottom: 28px;
        }
        label { display: block; margin: 16px 0 8px; }
        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #dce1e8;
            border-radius: 8px;
            font-size: 16px;
        }
        button {
            width: 100%;
            margin-top: 24px;
            padding: 14px;
            border: 0;
            border-radius: 8px;
            background: #202938;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }
        .error {
            padding: 12px;
            margin-bottom: 16px;
            color: #a52828;
            background: #fff0f0;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <main class="login-card">
        <h1>Vendo</h1>
        <p class="subtitle">لوحة إدارة المنصة</p>

        @if ($errors->any())
            <div class="error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.submit') }}">
            @csrf

            <label for="login">البريد الإلكتروني أو رقم الهاتف</label>
            <input
                id="login"
                name="login"
                type="text"
                value="{{ old('login') }}"
                required
                autofocus
            >

            <label for="password">كلمة المرور</label>
            <input
                id="password"
                name="password"
                type="password"
                required
            >

            <button type="submit">تسجيل الدخول</button>
        </form>
    </main>
</body>
</html>
