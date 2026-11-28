<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your 11med data</title>
    <style>
        body { font-family: -apple-system, "Segoe UI", Roboto, sans-serif; color: #0A1628; background: #F7FAFA; margin: 0; padding: 24px; }
        main { max-width: 720px; margin: 0 auto; background: #FFFFFF; border-radius: 22px; padding: 24px; }
        h1 { font-size: 26px; margin: 0 0 4px; }
        p { font-size: 15px; line-height: 1.5; color: #5A6B7A; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 15px; }
        th, td { text-align: left; padding: 10px 0; border-bottom: 1px solid #E4EFED; }
        td.count { text-align: right; }
        dt { font-size: 12px; color: #5A6B7A; margin-top: 12px; }
        dd { margin: 2px 0 0; font-size: 15px; }
    </style>
</head>
<body>
<main>
    <h1>Your 11med data</h1>
    <p>Exported {{ $exported }} (Lagos time). Every record is in <strong>data.json</strong> beside this page, by section, as 11med stores it. This page says what it holds.</p>

    <dl>
        <dt>Name</dt>
        <dd>{{ $user->full_name }}</dd>
        <dt>Phone number</dt>
        <dd>{{ $user->phone ?? 'None' }}</dd>
        <dt>Email</dt>
        <dd>{{ $user->email ?? 'None' }}</dd>
    </dl>

    <table>
        <thead>
        <tr><th>Section</th><th class="count">Records</th></tr>
        </thead>
        <tbody>
        @foreach ($sections as $key => $section)
            <tr><td>{{ $section['title'] }} <small>({{ $key }})</small></td><td class="count">{{ number_format($section['rows']) }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <p>Files you uploaded, such as photos and test results, are listed in their records and not copied here. Open them in the app.</p>
</main>
</body>
</html>
