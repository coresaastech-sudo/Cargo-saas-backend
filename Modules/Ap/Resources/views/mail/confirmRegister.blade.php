<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>FIBA</title>
</head>

<body style="margin: 0;">
    <div>
        <center style="margin:0;padding:50px 0;background-color:#ebeff8">
            <table style="width:570px;font-family:'Tahoma';text-align:left;font-size:14px;margin:0" border="0"
                cellpadding="0" cellspacing="0">
                <tbody>
                    <tr>
                        <td>
                            <table style="width:100%;background-color:white;margin:0;padding:50px;border-radius:10px"
                                border="0" cellpadding="0" cellspacing="0">
                                <tbody>
                                    <tr>
                                        <th colspan="2">
                                            @include('ap::mail.mail-logo')
                                        </th>
                                    </tr>
                                    <tr>
                                        <td colspan="3"
                                            style="color:black;padding:10px 0 0 0;font-size:18px;font-weight:900">
                                            Сайн байна уу,
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="color:#a0aec0;font-size:14px;padding:10px 0">
                                            Та доорх баталгаажуулах кодийг ашиглан
                                            бүртгэлээ идэвхижүүлнэ үү.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:20px 0 0 0;">
                                            <div
                                                style="border:2px dashed rgba(28,110,164,0.14);border-radius:20px;padding:10px;width:100%">
                                                <table
                                                    style="background-color:#f2f6fc;width:100%;margin:auto;border-radius:15px;padding:20px 0;text-align:center; height: 140px;"
                                                    border="0" cellpadding="0" cellspacing="0">
                                                    <tbody>
                                                        <tr>
                                                            <th
                                                                style="font-size:32px;letter-spacing:4px;text-align:center;color:#2B3F6C">
                                                                {{ $data['passtoken'] }}
                                                            </th>
                                                        </tr>
                                                        <tr>
                                                            <td colspan="3">
                                                                <a href="{{ $data['hostname'] }}/confirm/{{ $data['passtoken'] }}"
                                                                    id="contBtn" target="_blank"
                                                                    style="text-decoration: none;
                                                                        background: #2B3F6C;color: #fff;
                                                                        margin-top: 8em;
                                                                        padding: .8em 3em;
                                                                        -webkit-box-shadow: 0px 15px 30px rgba(50, 50, 50, 0.21);
                                                                        -moz-box-shadow: 0px 15px 30px rgba(50, 50, 50, 0.21);
                                                                        box-shadow: 0px 15px 30px rgba(50, 50, 50, 0.21);
                                                                        border-radius: 25px;-webkit-transition: all .4s ease;
                                                                        -moz-transition: all .4s ease;-o-transition: all .4s ease;transition: all .4s ease;
                                                                        font-family: 'Raleway', sans-serif; text-decoration: none; outline: none;">
                                                                    Баталгаажуулах
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    @include('ap::mail.mail-footer')
                </tbody>
            </table>
        </center>
        <div class="yj6qo"></div>
        <div class="adL">
        </div>
    </div>
</body>

</html>
