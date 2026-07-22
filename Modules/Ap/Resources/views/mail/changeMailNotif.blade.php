<!DOCTYPE html>
<html>

<head>
    <title>Me - FIBA</title>
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
                                            Таны ME апп системд бүртгэлтэй {{ $oldemail }} имэйл хаягийг
                                            {{ $newemail }} хаягаар шинэчлэх хүсэлт илгээсэн байна.
                                        </td>
                                    </tr>
                                    @if (!$isown)
                                        <tr>
                                            <td colspan="3" style="color:#a0aec0;font-size:14px;padding:10px 0">
                                                <b>Анхааруулга: </b> Хэрэв та цахим хаяг шинэчлэх хүсэлт илгээгээгүй бол
                                                {{ $instname }} байгууллагад яаралтай хандана уу. Утас:
                                                {{ $phone }}
                                            </td>
                                        </tr>
                                    @endif
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
