@component('mail::message')
# Сайн байна уу,

Та нууц үг сэргээх хүсэлт гаргасан байна.

Нууц үг сэргээх холбоос:

@component('mail::button', ['url' => $url . '/resetPassword/' . $token])
Нууц үг сэргээх
@endcomponent

Холбоосоо бусадтай бүү хуваалцаарай. Хэрэв та энэ хүсэлтийг гаргаагүй бол энэ имэйлийг үл тооно уу.

Хүндэтгэсэн,<br>
{{ config('app.name') }}
@endcomponent
