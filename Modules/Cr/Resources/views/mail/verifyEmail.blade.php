<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <title>Имэйл баталгаажуулалт</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f7; font-family: Tahoma, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding: 50px 0;">
        <tr>
            <td align="center">
                <table width="570" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 10px; padding: 40px; text-align: left;">
                    <tr>
                        <td align="center" style="padding-bottom: 20px;">
                            {{-- Your Logo --}}
                            @include('cr::mail.mail-mecore-logo')
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 18px; font-weight: bold; color: #333333;">
                            Сайн байна уу{{ isset($userName) ? ', ' . $userName : '' }}?
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 14px; color: #555555; padding-top: 15px;">
                            Та манай системд бүртгүүлсэн байна. Имэйл хаягаа баталгаажуулахын тулд доорх товч дээр дарна уу.
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding: 30px 0;">
                            <a href="{{$hostname}}/api/verify-email/{{$token}}"
                               style="background-color: #2B3F6C; color: white; padding: 15px 30px; border-radius: 6px;
                               text-decoration: none; font-weight: bold; display: inline-block;">
                                Имэйл баталгаажуулах
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 13px; color: #a0aec0; text-align: center; padding-top: 20px;">
                            Хэрвээ та энэ имэйлийг санаандгүй авсан бол ямар нэгэн арга хэмжээ авах шаардлагагүй.
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top: 1px solid #e2e8f0; padding-top: 30px; font-size: 13px; color: #718096; text-align: center;">
                            {{ $companyName ?? 'FIBA ХХК' }} &copy; {{ now()->year }}. Бүх эрх хуулиар хамгаалагдсан.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>