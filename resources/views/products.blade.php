@php
    $frontendUrl = rtrim((string) config('checkout.frontend_url', config('app.url')), '/');
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products | Heart Made</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: clamp(20px, 5vw, 48px); background: #f7f3ee; color: #263b32; font-family: Arial, Helvetica, sans-serif; }
        .container { max-width: 1000px; margin: auto; }
        .products { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 20px; }
        .card { min-width: 0; padding: 24px; border: 1px solid #e8dfd7; border-radius: 16px; background: #fff; }
        .card h2 { overflow-wrap: anywhere; font-size: 20px; }
        .price { color: #456d56; font-size: 24px; font-weight: bold; }
        .product-button { display: block; margin-top: 20px; padding: 12px; border-radius: 999px; background: #263b32; color: #fff; text-align: center; text-decoration: none; }
        .product-button:focus-visible { outline: 3px solid #a65f42; outline-offset: 4px; }
        .message { padding: 16px; background: #fff; border-radius: 12px; line-height: 1.7; }
        .intro { color: #786e68; line-height: 1.7; }
    </style>
</head>
<body>
    <main class="container">
        <h1>Our products</h1>
        <p class="intro">Explore our creations and complete your order through the store checkout.</p>
        @if (isset($errors) && $errors->any())
            <p class="message" role="alert">We could not complete your request. Please return to the store and try again.</p>
        @endif
        <div class="products">
            @forelse (($products ?? []) as $product)
                <article class="card">
                    <h2>{{ $product->name }}</h2>
                    <div class="price">JD {{ number_format((float) $product->price, 2) }}</div>
                    <a class="product-button" href="{{ $frontendUrl }}/#/product?id={{ rawurlencode((string) $product->id) }}">View product</a>
                </article>
            @empty
                <p class="message">No products are available at the moment.</p>
            @endforelse
        </div>
        <p><a href="{{ $frontendUrl }}/#/catalog">Open the full catalog</a></p>
    </main>
</body>
</html>
