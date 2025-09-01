<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メール認証のご案内</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans JP', 'Hiragino Kaku Gothic ProN', Meiryo, sans-serif; color: #222; }
        .container { max-width: 640px; margin: 0 auto; padding: 24px; }
        .btn { display: inline-block; padding: 12px 20px; background: #4F46E5; color: #fff !important; border-radius: 6px; text-decoration: none; }
        .mt { margin-top: 16px; }
        .small { color: #6b7280; font-size: 12px; }
    </style>
    </head>
<body>
    <div class="container">
        <p>{{ $email }} 様</p>
        <p>Lexis への仮登録ありがとうございます。以下のボタンからメール認証を完了してください。</p>

        <p class="mt">
            <a class="btn" href="{{ $verifyUrl }}" target="_blank" rel="noopener noreferrer">メール認証を完了する</a>
        </p>

        <p class="mt">もしボタンがうまく開けない場合は、以下のURLをブラウザにコピーして開いてください。</p>
        <p class="small">{{ $verifyUrl }}</p>

        <hr class="mt"/>
        <p class="small">このメールに心当たりがない場合は、破棄してください。</p>
    </div>
</body>
</html>


