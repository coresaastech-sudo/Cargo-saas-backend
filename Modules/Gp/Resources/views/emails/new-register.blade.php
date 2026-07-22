@component('mail::message')
# Сайн байна уу,

[{{ $url }}]({{ $url }}) системд нэвтрэх эрх идэвхжлээ.

Нэвтрэх нэр: **{{ $username }}**

Та доорх товч дээр дарж нууц үгээ шинэчилнэ үү:

@component('mail::button', ['url' => $url . '/resetPassword/' . $token])
Нууц үг шинэчлэх
@endcomponent

Токены хүчинтэй хугацаа: **{{ $token_life_time }} минут**

Хэрвээ хугацаа дууссан бол "Нууц үг мартсан" хэсгээр сэргээнэ үү.

Хүндэтгэсэн,<br>
{{ config('app.name') }}
@endcomponent
