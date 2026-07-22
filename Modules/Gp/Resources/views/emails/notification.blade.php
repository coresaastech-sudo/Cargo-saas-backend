@component('mail::message')
# Сайн байна уу,

{{ empty($description) ? $title : $description }}

Хүндэтгэсэн,<br>
{{ config('app.name') }}
@endcomponent
