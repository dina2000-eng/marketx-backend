<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Vendo | لوحة الإدارة')</title>

    <style>
        :root {
            --primary: #5b5ce2;
            --primary-dark: #4546c8;
            --sidebar: #171a2b;
            --sidebar-muted: #a5abc0;
            --bg: #f5f6fb;
            --surface: #ffffff;
            --text: #20243a;
            --muted: #858ba0;
            --border: #eceef4;
            --success: #16865b;
            --danger: #d84b59;
            --warning: #d99028;
            --shadow: 0 8px 28px rgba(30, 35, 70, .05);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
        }

        a { color: inherit; text-decoration: none; }
        button, input, select { font: inherit; }

        .admin-shell {
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            width: 260px;
            background: var(--sidebar);
            color: white;
            padding: 28px 18px;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 10px 32px;
            border-bottom: 1px solid rgba(255,255,255,.09);
        }

        .brand-mark {
            width: 42px;
            height: 42px;
            border-radius: 13px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #7778ff, #4c4dd0);
            font-size: 22px;
            font-weight: 800;
        }

        .brand-name { font-size: 22px; font-weight: 800; letter-spacing: .3px; }
        .brand-caption { color: var(--sidebar-muted); font-size: 11px; margin-top: 3px; }

        .nav-label {
            color: #777e98;
            font-size: 11px;
            font-weight: 700;
            padding: 28px 12px 12px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 13px 14px;
            margin: 3px 0;
            border-radius: 10px;
            color: var(--sidebar-muted);
            font-size: 14px;
            transition: .2s;
        }

        .nav-link:hover {
            color: white;
            background: rgba(255,255,255,.07);
        }

        .nav-link.active {
            color: white;
            background: linear-gradient(100deg, #5b5ce2, #494ac5);
            box-shadow: 0 6px 16px rgba(75,76,205,.25);
        }

        .nav-icon {
            width: 22px;
            text-align: center;
            font-size: 17px;
        }

        .sidebar-bottom {
            margin-top: auto;
            padding-top: 24px;
            border-top: 1px solid rgba(255,255,255,.09);
        }

        .logout-button {
            width: 100%;
            border: 0;
            background: rgba(255,255,255,.07);
            color: #e4e6ef;
            padding: 12px;
            border-radius: 10px;
            cursor: pointer;
            text-align: right;
        }

        .logout-button:hover { background: rgba(216,75,89,.18); color: #ffb9c0; }

        .main-area { min-width: 0; flex: 1; }

        .topbar {
            min-height: 82px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 36px;
        }

        .page-heading h1 { margin: 0; font-size: 21px; font-weight: 750; }
        .page-heading p { margin: 6px 0 0; color: var(--muted); font-size: 13px; }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 11px;
            color: var(--muted);
            font-size: 13px;
        }

        .avatar {
            width: 39px;
            height: 39px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: white;
            background: #5b5ce2;
            font-weight: 700;
        }

        .content { padding: 30px 36px 45px; max-width: 1600px; margin: auto; }

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--shadow);
        }

        .panel-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 22px;
        }

        .panel-heading h2 { margin: 0; font-size: 17px; }
        .panel-heading p { margin: 6px 0 0; color: var(--muted); font-size: 13px; }

        .success, .error, .empty {
            padding: 14px 17px;
            border-radius: 11px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .success { background: #e9f8f0; color: #14734c; }
        .error { background: #fff0f1; color: #ad3542; }
        .empty { background: #f5f6fa; color: #777e91; text-align: center; }

        .status {
            display: inline-flex;
            align-items: center;
            padding: 6px 11px;
            border-radius: 30px;
            background: #f0f1f6;
            color: #626a80;
            font-size: 12px;
            font-weight: 650;
        }

        .status.active, .status.paid, .status.delivered {
            background: #e8f7ef; color: #168052;
        }

        .status.pending { background: #fff5df; color: #986713; }
        .status.processing, .status.confirmed, .status.shipped {
            background: #edf1ff; color: #4d5dc0;
        }

        .status.inactive, .status.failed, .status.cancelled, .status.rejected {
            background: #fff0f1; color: #bd3e4c;
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 7px;
            border: 0;
            border-radius: 9px;
            padding: 10px 16px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 650;
            transition: .2s;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background: #e8f7ef; color: #168052; }
        .btn-danger { background: #fff0f1; color: #bd3e4c; }
        .btn-warning { background: #fff5df; color: #986713; }

        input, select, textarea {
            border: 1px solid #e1e4ed;
            border-radius: 9px;
            padding: 11px 13px;
            background: white;
            color: var(--text);
            outline: none;
        }

        input:focus, select:focus, textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(91,92,226,.1);
        }


.pagination {
    margin-top: 22px;
    padding-top: 16px;
    border-top: 1px solid #edf0f5;
}

.pagination nav {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    flex-wrap: wrap;
}

.pagination nav > div:first-child {
    display: none;
}

.pagination nav > div:last-child > span,
.pagination nav > div:last-child > a {
    display: flex;
    align-items: center;
    gap: 5px;
}

.pagination nav a,
.pagination nav span[aria-current="page"] > span,
.pagination nav span[aria-disabled="true"] > span {
    min-width: 32px;
    height: 32px;
    padding: 0 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e5e7eb;
    border-radius: 7px;
    background: white;
    color: #64748b;
    font-size: 12px;
    text-decoration: none;
}

.pagination nav span[aria-current="page"] > span {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.pagination nav a:hover {
    color: var(--primary);
    border-color: var(--primary);
}

.pagination nav svg {
    width: 12px;
    height: 12px;
}

.pagination nav span[aria-disabled="true"] > span {
    color: #cbd5e1;
}


        @media (max-width: 900px) {
            .sidebar { width: 220px; }
            .topbar { padding: 18px 22px; }
            .content { padding: 24px 22px; }
        }

        @media (max-width: 650px) {
            .admin-shell { display: block; }
            .sidebar { width: 100%; padding: 15px; }
            .brand { padding: 0 5px 14px; }
            .nav-label, .sidebar-bottom { display: none; }
            .sidebar nav {
                display: flex;
                gap: 7px;
                overflow-x: auto;
                padding-top: 12px;
            }
            .nav-link { flex-shrink: 0; padding: 10px 12px; margin: 0; }
            .nav-link span:last-child { white-space: nowrap; }
            .topbar { min-height: 70px; padding: 15px; }
            .page-heading h1 { font-size: 18px; }
            .admin-profile > span:last-child { display: none; }
            .content { padding: 17px 13px 30px; }
            .panel { padding: 17px; border-radius: 13px; }
        }
    </style>
</head>
<body>
<div class="admin-shell">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">V</div>
            <div>
                <div class="brand-name">Vendo</div>
                <div class="brand-caption">منصة إدارة المتاجر</div>
            </div>
        </div>

        <nav>
            <div class="nav-label">القائمة الرئيسية</div>

            <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
               href="{{ route('admin.dashboard') }}">
                <span class="nav-icon">▦</span><span>لوحة التحكم والمنتجات</span>
            </a>

            <a class="nav-link {{ request()->routeIs('admin.products*') ? 'active' : '' }}"
               href="{{ route('admin.products') }}">
                <span class="nav-icon">▧</span><span>المنتجات</span>
            </a>

            <a class="nav-link {{ request()->routeIs('admin.stores*') ? 'active' : '' }}"
               href="{{ route('admin.stores') }}">
                <span class="nav-icon">▣</span><span>المتاجر</span>
            </a>

            <a class="nav-link {{ request()->routeIs('admin.vendor-requests*') ? 'active' : '' }}"
                href="{{ route('admin.vendor-requests') }}">
                 <span class="nav-icon">▣</span><span>طلبات البائعين</span>
            </a>

            <a class="nav-link {{ request()->routeIs('admin.orders*') ? 'active' : '' }}"
               href="{{ route('admin.orders') }}">
                <span class="nav-icon">▤</span><span>الطلبات</span>
            </a>

            <a class="nav-link {{ request()->routeIs('admin.categories*') ? 'active' : '' }}"
               href="{{ route('admin.categories') }}">
                <span class="nav-icon">▧</span><span>التصنيفات</span>
            </a>

            <a class="nav-link {{ request()->routeIs('admin.payouts*') ? 'active' : '' }}"
               href="{{ route('admin.payouts') }}">
                <span class="nav-icon">◈</span><span>طلبات السحب</span>
            </a>
        </nav>

        <div class="sidebar-bottom">
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button class="logout-button" type="submit">↪ تسجيل الخروج</button>
            </form>
        </div>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <div class="page-heading">
                <h1>@yield('page-title', 'لوحة التحكم')</h1>
                <p>@yield('page-description', 'إدارة ومتابعة منصة Vendo')</p>
            </div>

            <div class="admin-profile">
                <div class="avatar">A</div>
                <span>مدير المنصة</span>
            </div>
        </header>

        <main class="content">
            @if (session('success'))
                <div class="success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
