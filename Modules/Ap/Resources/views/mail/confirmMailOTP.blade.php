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
                                        <td colspan="3"
                                            style="color:black;padding:10px 0 0 0;font-size:18px;font-weight:900">
                                            Сайн байна уу,
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="color:#a0aec0;font-size:14px;padding:10px 0">
                                            Та {{ $appname }} аппликейшнд энэ кодыг оруулж мэйлээ баталгаажуулна уу.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:20px 0 0 0;">
                                            <div
                                                style="border:2px dashed rgba(28,110,164,0.14);border-radius:20px;padding:10px;width:100%">
                                                <table style="background-color:#f2f6fc;width:100%;margin:auto;border-radius:15px;padding:20px 0;text-align:center;table-layout: fixed;" border="0" cellpadding="0" cellspacing="0">
                                                    <tbody>
                                                        <tr>
                                                            <td colspan="4" style="padding: 10px; text-align: center;margin: auto;">
                                                                <h1>&nbsp;</h1>
                                                            </td>
                                                            <td colspan="2" style="padding: 10px; text-align: center;margin: auto;">
                                                                <h1>{{ $otp[0] }}</h1>
                                                            </td>
                                                            <td colspan="2" style="padding: 10px; text-align: center;margin: auto;">
                                                                <h1>{{ $otp[1] }}</h1>
                                                            </td>
                                                            <td colspan="2" style="padding: 10px; text-align: center;margin: auto;">
                                                                <h1>{{ $otp[2] }}</h1>
                                                            </td>
                                                            <td colspan="2" style="padding: 10px; text-align: center;margin: auto;">
                                                                <h1>{{ $otp[3] }}</h1>
                                                            </td>
                                                            <td colspan="2" style="padding: 10px; text-align: center;margin: auto;">
                                                                <h1>{{ $otp[4] }}</h1>
                                                            </td>
                                                            <td colspan="2" style="padding: 10px; text-align: center;margin: auto;">
                                                                <h1>{{ $otp[5] }}</h1>
                                                            </td>
                                                            <td colspan="4" style="padding: 10px; text-align: center;margin: auto;">
                                                                <h1>&nbsp;</h1>
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
