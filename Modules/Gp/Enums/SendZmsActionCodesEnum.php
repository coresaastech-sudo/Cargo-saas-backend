<?php

namespace Modules\Gp\Enums;

use App\Enum\Enum;

// Зээлийн мэдээ нийлүүлэх ActionCode-ууд
class SendZmsActionCodesEnum extends Enum {
    const ln902020 = 'ln902020'; // Зээл - Бэлнээр олгох
    const ln902021 = 'ln902021'; // Зээл - Бэлэн бусаар олгох
    
    const ln902010 = 'ln902010'; // Зээл - Бэлнээр төлөх
    const ln902011 = 'ln902011'; // Зээл - Бэлэн бусаар төлөх
    const ln802011 = 'ln802011'; // Зээл - Бэлэн бусаар төлөх - Сонголтоор
    const ln902090 = 'ln902090'; // Зээл - Хаах бэлнээр
    const ln902091 = 'ln902091'; // Зээл - Хаах бэлэн бусаар
    const ln902081 = 'ln902081'; // Зээл - Ангилал шилжүүлэх
    const ln902036 = 'ln902036'; // Зээл - Хүү урьдчилан төлөх бэлнээр
    const ln902037 = 'ln902037'; // Зээл - Хүү урьдчилан төлөх бэлэн бусаар
    const ln902053 = 'ln902053'; // Зээл - Нэмэгдүүлсэн хүү капитализэшн хийх
    const ln902030 = 'ln902030'; // Зээл - Үндсэн хүү бэлэн төлөх
    const ln902031 = 'ln902031'; // Зээл - Үндсэн хүү бэлэн бусаар төлөх
    const ln902032 = 'ln902032'; // Зээл - Комитмент хүү бэлэн төлөх
    const ln902033 = 'ln902033'; // Зээл - Комитмент хүү бэлэн бусаар төлөх
    const ln902034 = 'ln902034'; // Зээл - Нэмэгдүүлсэн хүү бэлэн төлөх
    const ln902035 = 'ln902035'; // Зээл - Нэмэгдүүлсэн хүү бэлэн бусаар төлөх

   // Custom method to get all values
   public static function getValues(): array
   {
       return array_values((new \ReflectionClass(__CLASS__))->getConstants());
   }
}
