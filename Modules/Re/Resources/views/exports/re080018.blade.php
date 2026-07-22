<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Sainscore</title>
    <style>
        * {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 0;
        }

        table {
            border-spacing: 0;
            border-collapse: collapse;
        }

        .td {
            border: solid 0.031rem #000;
            line-height: 0.9em;
            padding: 2px 1px;
        }

        .text-right {
            text-align: right !important
        }
    </style>
</head>

<div style="margin-top: 40px; padding-right: 20px; padding-left: 20px;">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Журнал</th>
                <th>Гүйлгээ хийсэн огноо</th>
                <th>Дансны нэр</th>
                <th>Данс</th>
                <th>Дансны валют</th>
                <th>Гүйлгээний дүн</th>
                <th>Ханш</th>
                <th>ЕД</th>
                <th>Үндсэн зээл төлөлт</th>
                <th>Хүү төлөлт</th>
                <th>Гүйлгээний утга</th>
                <th>Харьцсан дансны нэр</th>
                <th>Харьцсан данс</th>
                <th>Харьцсан дансны валют</th>
                <th>Харьцсан ханш</th>
                <th>Харьцсан ЕД</th>
                <th>Дүн төгрөгөөр</th>
                <th>Зээлийн төрөл</th>
                <th>Гүйлгээ нийсэн теллер</th>
                <th>Гүйлгээний код</th>
                <th>Гүйлгээний нэр</th>

            </tr>
        </thead>
        <tbody>
            @foreach ($datas as $data)
                <tr>
                    <td class="text-center">{{ $data->jrno }}</td>
                    <td class="text-center">{{ $data->txndate }}</td>
                    <td>{{ $data->custname }}</td>
                    <td>{{ $data->retailacntno }}</td>
                    <td class="text-center">{{ $data->curcode }}</td>
                    <td class="text-right">{{ number_format($data->txnamount, 2) }}</td>
                    <td class="text-right">{{ number_format($data->currate, 2) }}</td>
                    <td>{{ $data->gl }}</td>
                    <td class="text-right">{{ number_format($data->zeel, 2) }}</td>
                    <td class="text-right">{{ number_format($data->khuu, 2) }}</td>
                    <td>{{ $data->txndesc }}</td>
                    <td>{{ $data->contcustname }}</td>
                    <td>{{ $data->contacntno }}</td>
                    <td class="text-center">{{ $data->contcurcode }}</td>
                    <td class="text-right">{{ number_format($data->contcurrate, 2) }}</td>
                    <td>{{ $data->contgl }}</td>
                    <td class="text-right">{{ number_format($data->amountmnt, 2) }}</td>
                    <td>{{ $data->name }}</td>
                    <td>{{ $data->tellername }}</td>
                    <td class="text-center">{{ $data->instid }}</td>
                    <td>{{ $data->txncode }}</td>
                    <td>{{ $data->processname }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
