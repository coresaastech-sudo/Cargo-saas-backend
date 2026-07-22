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
                                            Эрхэм харилцагч {{ $data['firstname'] }} таньд энэ өдрийн мэнд хүргэе
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="color:#a0aec0;font-size:14px;padding:10px 0">
                                            Таньд Me вэб application {{ $data['hostname'] }} -д нэвтрэх эрх нээгдлээ.
                                            Та дараах линкээр нэвтэрч нууц үгээ шинээр тохируулна уу
                                        </td>
                                    </tr>
                                    <tr>

                                        <td style="padding:20px 0 0 0;">
                                            <div
                                                style="border:2px dashed rgba(28,110,164,0.14);border-radius:20px;padding:10px;width:100%">
                                                <table
                                                    style="background-color:#f2f6fc;width:100%;margin:auto;border-radius:15px;padding:20px 0;text-align:center;"
                                                    border="0" cellpadding="0" cellspacing="0">
                                                    <tbody>
                                                        <tr>
                                                            <td colspan="3">
                                                                <a href="{{ $data['hostname'] }}/resetPassword/{{ $data['token'] }}"
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
                                                                    Нууц үг тохируулах
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
