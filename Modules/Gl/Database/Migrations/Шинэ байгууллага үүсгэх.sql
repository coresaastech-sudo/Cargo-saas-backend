1. Гараар байгууллага үүсгэх /Гараар/
    1. Процесс код байгууллагад өгөх
    2. Валют үүсгэх
    3. Салбар үүсгэх /Гараар/
    4. Түр данс үүсгэх /Гараар/
    5. Өдөр тохируулах
        select * from GP_inst_seq where instid = 4;
2. Хэрэглэгч
    1. Эрхийн Бүлэг /Гараар/
    2. Хэрэглэгч бүртгэх /Гараар/

2. Ерөнхий дэвтэр
    1. Хураангуй ерөнхий дэвтэр үүсгэх
    2. Бүлэг үүсгэх
    insert into gl_account_class
(
	class,
    name,
    name2,
    type,
    balmoving,
    listorder,
    statusid,
    instid,
    created_by,
    updated_by,
    created_at,
    updated_at
)
SELECT
    class,
    name,
    name2,
    type,
    balmoving,
    listorder,
    statusid,
    3 AS instid,
    18 AS created_by,
    18 AS updated_by,
    CURRENT_TIMESTAMP AS created_at,
    CURRENT_TIMESTAMP AS updated_at
FROM
    gl_account_class
WHERE
    instid = 7
ORDER BY
    listorder;

    3. Ерөнхий дэвтэрийн данс үүсгэх
3. Бүтээгдэхүүн үүсгэх
    1. Депозит
    2. Зээл
    3. Барьцаа хөрөнгө
    4. Дотоод
    5. Тэнцэлийн гадуурхи
    6. Касс

4. Тайлан
5. Баримт тохируулах
