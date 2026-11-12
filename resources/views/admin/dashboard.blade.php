<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendo | إدارة المنتجات</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 30px 16px;
            background: #f4f5f7;
            color: #202938;
            font-family: Arial, sans-serif;
        }

        .container { max-width: 1100px; margin: auto; }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 28px;
        }

        h1 { margin: 0; }
        .subtitle { color: #788292; }

        button {
            border: 0;
            border-radius: 7px;
            padding: 10px 16px;
            cursor: pointer;
            font-size: 14px;
        }

        .logout button { background: #202938; color: white; }

        .panel {
            background: white;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 5px 20px #00000008;
        }

        .product {
            padding: 20px 0;
            border-bottom: 1px solid #edf0f3;
        }

        .product:last-child { border-bottom: 0; }
        .product h3 { margin: 0 0 12px; }

        .details {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 24px;
            color: #687385;
            font-size: 14px;
        }

        .price { color: #202938; font-weight: bold; }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }

        .approve { background: #168653; color: white; }
        .reject { background: #c83d3d; color: white; }

        .reason {
            padding: 9px;
            border: 1px solid #dce1e8;
            border-radius: 7px;
            min-width: 220px;
        }

        .success, .error, .empty {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .success { background: #e8f7ef; color: #146c43; }
        .error { background: #fff0f0; color: #a52828; }
        .empty { background: #f4f5f7; color: #687385; }

        .pagination { margin-top: 20px; }

        @media (max-width: 600px) {
            header { align-items: flex-start; }
            .panel { padding: 16px; }
            .reason { width: 100%; }
        }
    </style>
</head>

<body>
<div class="container">
    <header>
        <div>
            <h1>Vendo</h1>
            <p class="subtitle">لوحة إدارة المنصة — مراجعة المنتجات</p>
        </div>

        <form class="logout" method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit">تسجيل الخروج</button>
        </form>
    </header>

    <main class="panel">
        <h2>المنتجات بانتظار الموافقة</h2>

        @if (session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        @forelse ($products as $product)
            <article class="product">
                <h3>{{ $product->name }}</h3>

                <div class="details">
                    <span>المتجر: {{ $product->store?->name ?? 'غير محدد' }}</span>
                    <span>التصنيف: {{ $product->category?->name ?? 'بدون تصنيف' }}</span>
                    <span class="price">
                        السعر: {{ $product->base_price }} {{ $product->currency }}
                    </span>
                    <span>رقم المنتج: {{ $product->id }}</span>
                </div>

                @if ($product->description)
                    <p>{{ $product->description }}</p>
                @endif

                <div class="actions">
                    <form method="POST" action="{{ route('admin.products.approve', $product) }}">
                        @csrf
                        <button class="approve" type="submit">
                            الموافقة على المنتج
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.products.reject', $product) }}">
                        @csrf
                        <input
                            class="reason"
                            type="text"
                            name="rejection_reason"
                            placeholder="سبب الرفض (مطلوب)"
                            maxlength="1000"
                            required
                        >
                        <button class="reject" type="submit">رفض المنتج</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="empty">
                لا توجد منتجات بانتظار المراجعة حالياً.
            </div>
        @endforelse

        <div class="pagination">
            {{ $products->links() }}
        </div>
    </main>
</div>
</body>
</html>
