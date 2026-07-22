<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ипотектийн хариуцлага балансын гад дансыг дүн болгов
     * @return void
     */
    public function up()
    {
        Schema::table(
            'ad_ebarimt',
            function (Blueprint $table) {
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-амжилттай, -1 - Ибаримт буцаасан')->change();  
            }
        );
        Schema::table(
            'ad_login_activity_log',
            function (Blueprint $table) {
                $table->unsignedBigInteger('userid')->comment('Хэрэглэгч')->change();
                $table->string('agent', 255)->comment('User-Agent')->change();      
                $table->string('device_ip', 20)->comment('Нэвтрэх үед ашигласан төхөөрөмжийн IP хаяг')->change();  
                $table->string('channel', 10)->comment('Суваг')->change();      
                $table->string('deviceid', 100)->comment('Төхөөрөмжийн ID')->change();        
                $table->string('devicename', 100)->comment('Төхөөрөмжийн нэр')->change();          

            }
        );
        Schema::table(
            'ad_login_confirm_device',
            function (Blueprint $table) {
                $table->unsignedBigInteger('userid')->comment('Хэрэглэгч')->change();       
                $table->string('ip', 20)->comment('IP Address')->change();          
                $table->string('channel', 10)->comment('Суваг')->change();      
                $table->string('token', 255)->comment('Төхөөрөмжийн токен')->change();       

            }
        );
        Schema::table(
            'ap_acnt_cd',
            function (Blueprint $table) {
                $table->string('get_with_secure', 1)->nullable()->comment('Нууцлах эсэх')->change();            
                $table->decimal('od_fee', 23, 5)->nullable()->comment('Зээлийн шугамын шимтгэл')->change();            
                $table->decimal('ol_fee', 23, 5)->nullable()->comment('Limit хэтэрсний шимтгэл')->change();            
                $table->string('status_id', 4)->nullable()->comment('Төлөв')->change();         
                $table->string('status_id_name', 50)->nullable()->comment('Төлөвийн нэр')->change();       
                $table->string('status_id_name2', 50)->nullable()->comment('Төлөвийн нэр 2')->change();     
                $table->string('status_name', 20)->nullable()->comment('Дансны төлөвийн нэр')->change();          
                $table->string('status_name2', 50)->nullable()->comment('Дансны төлөвийн нэр')->change();         
                $table->string('status_sys', 1)->nullable()->comment('Системийн төлөв')->change();            	
                $table->smallInteger('sys_no')->nullable()->comment('Системийн дугаар')->change();            

            }
        );

        Schema::table(
            'ap_acnt_dp',
            function (Blueprint $table) {
                $table->smallInteger('flag_no_tb')->nullable()->default(0)->comment('Төрөл тоо')->change();
                $table->string('flag_no_tb_name', 50)->nullable()->comment('Төрөл нэр')->change();         
                $table->smallInteger('is_corp_acnt')->nullable()->comment('Байгууллагын данс')->change();  
                $table->string('is_corp_name', 50)->nullable()->comment('Байгууллагын нэр')->change();     
                $table->smallInteger('is_secure')->nullable()->comment('Аюулгүй байна')->change();       
                $table->smallInteger('read_bal')->nullable()->comment('Үлдэгдэл унших')->change();
                $table->smallInteger('read_name')->nullable()->comment('Нэр унших')->change();
                $table->smallInteger('read_tran')->nullable()->comment('Гүйлгээ унших')->change();
                $table->string('salary_acnt', 1)->nullable()->default(0)->comment('Цалингийн данс')->change();
            }
        );
        Schema::table(
            'ap_contract_sign_image',
            function (Blueprint $table) {
                $table->binary('image')->comment('Зураг')->change();
                $table->string('name')->comment('Зурагны нэр')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, -1-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change(); 
            }
        );
        Schema::table(
            'ap_cust',
            function (Blueprint $table) {
                $table->unsignedBigInteger('corrid')->nullable()->comment('Суурь системийн id')->change();
            }
        );
        Schema::table(
            'ap_txn_journal',
            function (Blueprint $table) {
                $table->string('identity_type', 20)->nullable()->comment('Баримт төрөл')->change();
            }
        );

        Schema::table(
            'ca_cash_bal',
            function (Blueprint $table) {
                $table->string('acntcode', 12)->comment('Дансны код')->change();
                $table->decimal('sbal', 23, 5)->comment('Эхлэл үлдэгдэл')->change();
                $table->decimal('bal', 23, 5)->comment('Кассын үлдэгдэл')->change();
                $table->decimal('ctbal', 23, 5)->comment('Кредит үлдэгдэл')->change();
                $table->decimal('dtbal', 23, 5)->comment('Дебит үлдэгдэл')->change();
                $table->string('curcode', 3)->comment('Валют')->change();
                $table->date('sdate')->comment('Огноо')->change(); 
                $table->unsignedBigInteger('userid')->comment('Хэрэглэгч')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );

        Schema::table(
            'ca_cash_list',
            function (Blueprint $table) {
                $table->string('acntcode', 12)->comment('Дансны код')->change();
                $table->string('name')->comment('Нэр')->change();
                $table->string('name2')->nullable()->comment('Нэр2')->change();
                $table->smallInteger('ismain')->comment('Үндсэн эсэх')->change();
                $table->string('brchno', 6)->comment('Салбарын дугаар')->change();
                $table->string('gl', 16)->comment('Ерөнхий дэвтрийн данс')->change();
                $table->unsignedBigInteger('userid')->comment('Хэрэглэгч')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();

            }
        );
        Schema::table(
            'cr_cust_add',
            function (Blueprint $table) {
                $table->bigInteger('custid')->comment('Харилцагчийн дугаар')->change();
                $table->smallInteger('custtypecode')->comment('Харилцагчийн төрлийн код, 0 - хувь хүн 1 - байгууллага')->change();
                $table->bigInteger('keyfield')->comment('Нэмэлт талбар')->change();
                $table->string('itemvalue', 2000)->nullable()->comment('Утга')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, -1-идэвхгүй')->change();

            }
        );
        Schema::table(
            'cr_cust_address',
            function (Blueprint $table) {
                $table->bigInteger('custid')->comment('Харилцагчийн дугаар')->change();
                $table->smallInteger('custtypecode')->comment('Харилцагчийн төрлийн код, 0 - хувь хүн 1 - байгууллага')->change();
                $table->smallInteger('addrtypecode')->comment('Хаягийн төрөл')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, -1-идэвхгүй')->change();
                $table->bigInteger('state')->nullable()->comment('Аймаг хот')->change();
                $table->bigInteger('region')->nullable()->comment('Сум дүүрэг')->change();
                $table->string('subregion', 10)->nullable()->comment('Баг хороо')->change();
                $table->string('address', 200)->nullable()->comment('Хаяг')->change();
                $table->string('zipcode', 10)->nullable()->comment('ЗИП код')->change();
                $table->string('w3w', 50)->nullable()->comment('Гурван үгт хаяг')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();

            }
        );
        Schema::table(
            'cr_cust_contact',
            function (Blueprint $table) {
                $table->bigInteger('custid')->comment('Харилцагчийн дугаар')->change();
                $table->smallInteger('contacttypecode')->comment('Холбоо барих төрөл')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, -1-идэвхгүй')->change();
                $table->string('contact', 200)->nullable()->comment('Холбоо барих')->change();
            }
        );
        Schema::table(
            'cr_cust_doc',
            function (Blueprint $table) {
                $table->bigInteger('custid')->comment('Харилцагчийн дугаар')->change();
                $table->smallInteger('custtypecode')->comment('Харилцагчийн төрлийн код, 0 - хувь хүн 1 - байгууллага')->change();
                $table->binary('file')->comment('Файл')->change();
                $table->string('name')->comment('Нэр')->change();
                $table->string('name2')->nullable()->comment('Нэр2')->change();
                $table->string('filename')->comment('Файл нэр')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1 -идэвхтэй, -1 -идэвхгүй')->change();
            }
        );
        Schema::table(
            'cr_cust_image',
            function (Blueprint $table) {
                $table->binary('image')->comment('Зураг')->change();
                $table->string('name')->comment('Нэр')->change();
                $table->string('filename')->comment('Файл нэр')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'cr_cust_ind',
            function (Blueprint $table) {
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1 -идэвхтэй, -1 -идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'cr_cust_msg',
            function (Blueprint $table) {
                $table->bigInteger('custid')->comment('Харилцагчийн дугаар')->change();
                $table->smallInteger('custtypecode')->comment('Харилцагчийн төрлийн код, 0 - хувь хүн 1 - байгууллага')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, -1-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'cr_cust_notification_config',
            function (Blueprint $table) {
                $table->bigInteger('custid')->comment('Харилцагчийн дугаар')->change();
                $table->string('notiftype', 5)->comment('Мэдэгдэлийн төрөл SMS - SMS мэдэгдэл, PUSH - Push мэдэгдэл, MAIL - Мэйл мэдэгдэл ')->change();
                $table->smallInteger('enabled')->comment('Мэдэгдэл тохируулсан эсэх 0 - Үгүй, 1 - Тийм')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'cr_cust_org',
            function (Blueprint $table) {
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй')->change();
            }
        );
        Schema::table(
            'cr_cust_relation',
            function (Blueprint $table) {
                $table->bigInteger('custid')->comment('Харилцагчийн дугаар')->change();
                $table->smallInteger('custtypecode')->comment('Харилцагчийн төрлийн код, 0 - хувь хүн 1 - байгууллага')->change();
                $table->bigInteger('custid2')->comment('Хамаарал бүхий харилцагч')->change();
                $table->smallInteger('custid2typecode')->comment('Харилцагчийн төрлийн код, 0 - хувь хүн 1 - байгууллага')->change();
                $table->smallInteger('reltypecode')->comment('Хамаарлын үндсэн төрөл')->change();
                $table->smallInteger('relsubtypecode')->comment('Хамаарлын дэд төрөл')->change();
                $table->date('begindate')->nullable()->comment('Эхлэх огноо')->change();
                $table->date('enddate')->nullable()->comment('Дуусах огноо')->change();
                $table->string('reldesc', 200)->nullable()->comment('Тодорхойлолт')->change();
                $table->string('brchno', 6)->comment('Салбарын дугаар')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, -1-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'cr_cust_salarydays',
            function (Blueprint $table) {
                $table->bigInteger('custid')->comment('Харилцагчийн дугаар')->change();
                $table->smallInteger('custtypecode')->comment('Харилцагчийн төрлийн код, 0 - хувь хүн 1 - байгууллага')->change();
                $table->smallInteger('salaryday')->comment('Цалингийн өдөр')->change();
                $table->string('name', 50)->comment('Нэр')->change();
                $table->string('name2', 50)->nullable()->comment('Нэр2')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, -1-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'cr_cust_sale_asset',
            function (Blueprint $table) {
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'cr_cust_secret',
            function (Blueprint $table) {
                $table->bigInteger('custid')->comment('Харилцагчийн дугаар')->change();
                $table->smallInteger('custtypecode')->comment('Харилцагчийн төрлийн код, 0 - хувь хүн 1 - байгууллага')->change();
                $table->smallInteger('questiontypecode')->comment('Нууц асуулт сонгох')->change();
                $table->smallInteger('is_inputquestion')->comment('Нууц асуулт гараас оруулах эсэх')->change();
                $table->string('question', 100)->nullable()->comment('Нууц асуулт')->change();
                $table->string('answer', 100)->comment('Нууц хариулт')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, -1-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'cr_cust_shareholder',
            function (Blueprint $table) {
                $table->bigInteger('custid')->comment('Харилцагчийн дугаар')->change();
                $table->smallInteger('custtypecode')->comment('Харилцагчийн төрлийн код, 0 - хувь хүн 1 - байгууллага')->change();
                $table->bigInteger('custid2')->comment('Хамаарал бүхий харилцагч')->change();
                $table->smallInteger('custid2typecode')->comment('Харилцагчийн төрлийн код, 0 - хувь хүн 1 - байгууллага')->change();
                $table->smallInteger('sharetypecode')->comment('Хувьцаа эзэмшигчийн төрөл')->change();
                $table->decimal('sharepercent', 6, 3)->comment('Эзэмшиж буй хувь')->change();
                $table->date('begindate')->nullable()->comment('Эхлэх огноо')->change();
                $table->string('desc', 200)->nullable()->comment('Тодорхойлолт')->change();
                $table->string('brchno', 6)->comment('Салбарын дугаар')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'cr_cust_sign',
            function (Blueprint $table) {
                $table->bigInteger('custid')->comment('Харилцагчийн дугаар')->change();
                $table->smallInteger('custtypecode')->comment('Харилцагчийн төрлийн код, 0 - хувь хүн 1 - байгууллагаЗураг')->change();
                $table->string('image')->nullable()->comment('Зураг')->change();
                $table->string('name')->comment('Нэр')->change();
                $table->string('name2')->nullable()->comment('Нэр2')->change();
                $table->integer('sign_level')->comment('Гарын үсгийн түвшин')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'cr_cust_sign_image',
            function (Blueprint $table) {
                $table->binary('image')->comment('Зураг')->change();
                $table->string('name')->comment('Нэр')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'dp_account_mor',
            function (Blueprint $table) {
                $table->smallInteger('morstatus')->default(-1)->comment('Төлөв')->change();
            }
        );
        Schema::table(
            'dp_roll_temp',
            function (Blueprint $table) {
                $table->string('acntno', 20)->comment('Дансны дугаар')->change();
                $table->string('brchno', 4)->nullable()->comment('Салбарын дугаар')->change();
                $table->string('curcode', 3)->nullable()->comment('Валют')->change();
                $table->string('newprodcode', 10)->nullable()->comment('Шилжих бүтээгдэхүүн')->change();
                $table->decimal('newcrintrate', 23, 8)->nullable()->comment('Шинэ хүүгийн хувь /Жил/')->change();

            }
        );
        Schema::table(
            'GP_dic_mains',
            function (Blueprint $table) {
                $table->string('vw_name', 100)->comment('View нэр')->change();                 
                $table->string('description', 100)->nullable()->comment('Гүйлгээний утга')->change(); 
            }
        );
        Schema::table(
            'GP_files',
            function (Blueprint $table) {
                $table->binary('file')->comment('Файл')->change();
                $table->string('name')->comment('Нэр')->change(); 
                $table->string('type', 10)->comment('Төрөл')->change(); 
            }
        );
        Schema::table(
            'GP_inst_branch',
            function (Blueprint $table) {
                $table->string('brchno', 4)->comment('Салбарын дугаар')->change();
                $table->string('name', 200)->comment('Нэр')->change();
                $table->string('name2', 200)->nullable()->comment('Нэр2')->change();
                $table->string('dirname', 50)->nullable()->comment('Захирлын нэр')->change();
                $table->string('dirname2', 50)->nullable()->comment('Захирлын нэр2')->change();
                $table->date('begindate')->comment('Эхлэх огноо')->change();
                $table->string('phone', 20)->nullable()->comment('Утас')->change();
                $table->string('fax', 20)->nullable()->comment('Факс')->change();
                $table->string('email', 50)->nullable()->comment('И-Мэйл')->change();
                $table->smallInteger('isonline')->default(1)->comment('Онлайн салбар эсэх')->change();
                $table->string('bankcode', 6)->nullable()->comment('Банкны код')->change();
                $table->string('blevel', 10)->nullable()->comment('Салбарын түвшин')->change();
                $table->string('biccode', 30)->nullable()->comment('BIC код')->change();
                $table->smallInteger('doestrade')->default(0)->comment('Зарагдсан эсэх')->change();
                $table->smallInteger('listorder')->nullable()->comment('Эрэмбэ')->change();
                $table->bigInteger('state')->nullable()->comment('Аймаг хот')->change();
                $table->bigInteger('region')->nullable()->comment('Сум дүүрэг')->change();
                $table->string('subregion', 10)->nullable()->comment('Баг хороо')->change();
                $table->string('address', 200)->nullable()->comment('Хаяг')->change();
                $table->string('zipcode', 10)->nullable()->comment('ЗИП код')->change();
                $table->string('w3w', 50)->nullable()->comment('Гурван үгт хаяг')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
                $table->smallInteger('statusid')->default(1)->comment('Төлөвийн код, 1-идэвхтэй, -2-идэвхгүй')->change();
            }
        );
        Schema::table(
            'GP_inst_cur_rate',
            function (Blueprint $table) {
                $table->string('rtypecode', 3)->comment('Гүйлгээний дефаулт ханшийн төрөл')->change();
                $table->string('curcode', 3)->comment('Валют')->change();
                $table->decimal('salerate', 23, 8)->default(0)->comment('Борлуулалтын хувь')->change();
                $table->decimal('buyrate', 23, 8)->default(0)->comment('Худалдан авах ханш')->change();
                $table->smallInteger('statusid')->default(1)->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'GP_inst_cur_rate_hist',
            function (Blueprint $table) {
                $table->string('rtypecode', 3)->comment('Гүйлгээний дефаулт ханшийн төрөл')->change();
                $table->string('curcode', 3)->comment('Валют')->change();
                $table->decimal('salerate', 23, 8)->default(0)->comment('Борлуулалтын хувь')->change();
                $table->decimal('buyrate', 23, 8)->default(0)->comment('Худалдан авах ханш')->change();
                $table->date('date')->comment('Огноо')->change();
                $table->smallInteger('statusid')->default(1)->comment('Төлөвийн код, 1-идэвхтэй, -1-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );

        Schema::table(
            'GP_inst_gp',
            function (Blueprint $table) {
                $table->string('itemname', 30)->comment('Нэр')->change();
                $table->string('itemdesc', 200)->nullable()->comment('Тодорхойлолт')->change();
                $table->string('itemdesc2', 200)->nullable()->comment('Тодорхойлолт 2')->change();
                $table->string('itemvalue', 2000)->comment('Утга')->change();
                $table->string('itemadditional', 400)->nullable()->comment('Нэмэлт')->change();
                $table->string('itemadditional2', 400)->nullable()->comment('Нэмэлт2')->change();
                $table->string('groupname', 30)->nullable()->comment('Бүлгийн нэр')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'GP_inst_list',
            function (Blueprint $table) {
                $table->string('name', 200)->comment('Нэр')->change();
                $table->string('name2', 200)->nullable()->comment('Нэр2')->change();
                $table->string('regno', 20)->nullable()->comment('Бүртгэлийн дугаар')->change();
                $table->date('stabledate')->nullable()->comment('Байгуулагдсан огноо')->change();
                $table->string('inst_typeid', 10)->nullable()->comment('Байгууллагын төрөл')->change();
                $table->string('color', 100)->nullable()->comment('Өнгө')->change();
                $table->string('logo', 100)->nullable()->comment('Лого')->change();
                $table->string('phone', 20)->nullable()->comment('Утас')->change();
                $table->string('email', 50)->nullable()->comment('И-Мэйл')->change();
                $table->string('cbegno', 20)->default(0)->comment('Эхлэх дугаар')->change();
                $table->string('cendno', 20)->default(0)->comment('Дуусах дугаар')->change();
                $table->string('cnextno', 20)->default(0)->comment('Дараагийн дугаар')->change();
                $table->string('acntbegno', 20)->default(0)->comment('Дансны эхлэлийн дугаар')->change();
                $table->string('acntendno', 20)->default(0)->comment('Дансны төгсгөлийн дугаар')->change();
                $table->string('acntnextno', 20)->default(0)->comment('Дараагийн дансны дугаар')->change();
                $table->string('appbegno', 20)->default(0)->comment('Програмын эхлэлийн дугаар')->change();
                $table->string('appendno', 20)->default(0)->comment('Програмын төгсгөлийн дугаар')->change();
                $table->string('appnextno', 20)->default(0)->comment('Дараагийн програмын дугаар')->change();
                $table->string('collbegno', 20)->default(0)->comment('Барьцаа хөрөнгийн эхлэлийн дугаар')->change();
                $table->string('collendno', 20)->default(0)->comment('Барьцаа хөрөнгийн төгсгөлийн дугаар')->change();
                $table->string('collnextno', 20)->default(0)->comment('Дараагийн барьцааны дугаар')->change();
                $table->string('deductionbegno', 20)->nullable()->comment('Суутгал эхлэлийн дугаар')->change();
                $table->string('deductionendno', 20)->nullable()->comment('Суутгал төгсгөлийн дугаар')->change();
                $table->string('deductionnextno', 20)->nullable()->comment('Дараагийн суутгалын дугаар')->change();
                $table->smallInteger('listorder')->nullable()->comment('Эрэмбэ')->change();
                $table->bigInteger('state')->nullable()->comment('Аймаг хот')->change();
                $table->bigInteger('region')->nullable()->comment('Сум дүүрэг')->change();
                $table->string('subregion', 10)->nullable()->comment('Баг хороо')->change();
                $table->string('street', 200)->nullable()->comment('Гудамж')->change();
                $table->string('zipcode', 10)->nullable()->comment('ЗИП код')->change();
                $table->string('w3w', 50)->nullable()->comment('Гурван үгт хаяг')->change();
                $table->smallInteger('statusid')->default(1)->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй')->change();
            }
        );
        Schema::table(
            'GP_inst_perms',
            function (Blueprint $table) {
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
                $table->string('moduleid', 20)->nullable()->comment('Модуль ID')->change();
                $table->string('AC', 8)->comment('Процессын код')->change();
                $table->smallInteger('isadmin')->nullable()->comment('Админ эсэх')->change();
                $table->smallInteger('statusid')->default(1)->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй')->change();
            }
        );
        Schema::table(
            'GP_inst_role_perms',
            function (Blueprint $table) {
                $table->string('AC', 8)->comment('Процессын код')->change();
                $table->smallInteger('statusid')->default(1)->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй')->change();
            }
        );
        Schema::table(
            'GP_inst_seq',
            function (Blueprint $table) {
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'GP_inst_user_roles',
            function (Blueprint $table) {
                $table->bigInteger('userid')->comment('Хэрэглэгч')->change();
                $table->bigInteger('roleid')->comment('Хариуцлагын дугаар')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
                $table->date('startdate')->comment('Эхэлсэн огноо')->change();
                $table->date('enddate')->comment('Дуусах огноо')->change();
                $table->smallInteger('statusid')->default(1)->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй')->change();
            }
        );
        Schema::table(
            'GP_photos',
            function (Blueprint $table) {
                $table->binary('photo')->comment('Зураг')->change();
                $table->string('name')->comment('Нэр')->change();
            }
        );
        Schema::table(
            'ia_account',
            function (Blueprint $table) {
                $table->string('transitacntno', 20)->nullable()->comment('Дансны дугаар')->change();
                $table->string('dpacntno', 20)->nullable()->comment('Дансны дугаар')->change();
            }
        );
        Schema::table(
            'ia_account_hist',
            function (Blueprint $table) {
                $table->string('transitacntno', 20)->nullable()->comment('Дансны дугаар')->change();
                $table->string('dpacntno', 20)->nullable()->comment('Дансны дугаар')->change();
            }
        );
        Schema::table(
            'ia_account_type',
            function (Blueprint $table) {
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();;
                $table->bigInteger('listorder')->default(0)->comment('Эрэмбэ')->change();
            }
        );
        Schema::table(
            'ia_ct_account',
            function (Blueprint $table) {
                $table->string('txndef', 2)->nullable()->comment('Гүйлгээний тодорхойлолт')->change();
                $table->string('relacntmod', 2)->nullable()->comment('Дансны модуль')->change();
                $table->string('relacntno', 20)->nullable()->comment('Дансны дугаар')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'ia_ct_account_add',
            function (Blueprint $table) {
                $table->string('acntno', 20)->comment('Дансны дугаар')->change();
                $table->bigInteger('keyfield')->comment('Нэмэлт талбар')->change();
                $table->string('itemvalue', 2000)->nullable()->comment('Утга')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, -1-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'ia_ct_account_hist',
            function (Blueprint $table) {
                $table->string('relacntmod', 2)->nullable()->comment('Дансны модуль')->change();
                $table->string('relacntno', 20)->nullable()->comment('Дансны дугаар')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'ia_ct_account_type',
            function (Blueprint $table) {
                $table->bigInteger('listorder')->default(0)->comment('Эрэмбэ')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'ia_ct_account_type_add',
            function (Blueprint $table) {
                $table->bigInteger('keyfield')->comment('Нэмэлт талбар')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'ln_account_add',
            function (Blueprint $table) {
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'ln_account_mor',
            function (Blueprint $table) {
                $table->smallInteger('morstatus')->default(-1)->comment('Төлөв')->change();
            }
        );
        Schema::table(
            'ln_account_type_add',
            function (Blueprint $table) {
                $table->string('prodcode', 10)->comment('Бүтээгдэхүүн')->change();
                $table->bigInteger('keyfield')->comment('Нэмэлт талбар')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'ln_account_type_appadd',
            function (Blueprint $table) {
                $table->string('prodcode', 10)->comment('Бүтээгдэхүүн')->change();
                $table->bigInteger('keyfield')->comment('Нэмэлт талбар')->change();
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();
            }
        );
        Schema::table(
            'ln_app',
            function (Blueprint $table) {
                $table->date('begdate')->nullable()->comment('Эхлэх огноо')->change();
                $table->date('enddate')->nullable()->comment('Дуусах огноо')->change();
                $table->smallInteger('approvetellerno')->default(0)->comment('Зөвшөөрсөн ажилтан')->change();
                $table->date('createdate')->nullable()->comment('Үүсгэсэн')->change();
                $table->smallInteger('bintrateruleid')->default(0)->comment('Хүүний хувь тооцох дүрмийн id')->change();
                $table->smallInteger('approvebintrateruleid')->default(0)->comment('Зөвшөөрсөн хүүний дүрэм')->change();
                $table->string('greenpurpcode', 6)->nullable()->comment('Ногоон зээлийн зориулалт')->change();
                $table->string('greensubpurpcode', 6)->nullable()->comment('Ногоон зээлийн дэд зориулалт')->change();

            }
        );
        Schema::table(
            'ln_app_add',
            function (Blueprint $table) {
                $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй')->change();
                $table->bigInteger('instid')->comment('Байгууллагын дугаар')->change();

            }
        );
        Schema::table(
            'ln_mor_type',
            function (Blueprint $table) {
                $table->smallInteger('yesinsurance')->default(0)->comment('Даатгал шаардах')->change();
                $table->decimal('custpercent', 5, 2)->default(0)->comment('Харилцагчийн хувь')->change();
                $table->decimal('bankpercent', 5, 2)->default(0)->comment('Банкны хувь')->change();
                $table->decimal('custamount', 23, 8)->default(0)->comment('Үнэлгээний дүн')->change();
                $table->decimal('bankamount', 23, 8)->default(0)->comment('Банкны үнэлгээний дүн')->change();
                $table->smallInteger('calcmethod')->default(0)->comment('Үнэлгээ тооцох хэлбэр')->change();
                $table->string('regmask', 100)->nullable()->comment('Гэрчилгээний макс')->change();

            }
        );
        Schema::table(
            'ln_mor_add',
            function (Blueprint $table) {
                $table->bigInteger('keyfield')->comment('Нэмэлт талбар')->change();

            }
        );
        Schema::table(
            'ln_txn',
            function (Blueprint $table) {
                $table->smallInteger('instid')->comment('Байгууллагын дугаар')->change();

            }
        );
        Schema::table(
            'log_changes',
            function (Blueprint $table) {
                $table->string('event')->comment('Үйл явдал')->change();
                $table->text('old_values')->nullable()->comment('Хуучин утга')->change();
                $table->text('new_values')->nullable()->comment('Шинэ утга')->change();
                $table->text('url')->nullable()->comment('URL')->change();
                $table->string('user_agent', 1023)->nullable()->comment('User-Agent')->change();

            }
        );
        Schema::table(
            'tr_cur_rate_hist',
            function (Blueprint $table) {
                $table->date('date')->comment('Огноо')->change();
                $table->string('curcode', 3)->comment('Валют')->change();

            }
        );
        Schema::table(
            'tr_glretail_entry',
            function (Blueprint $table) {
                $table->string('retailacntmod', 2)->nullable()->comment('Модуль')->change();

            }
        );
        Schema::table(
            'tr_journal',
            function (Blueprint $table) {
                $table->string('retailacntmod', 2)->nullable()->comment('Модуль')->change();
                $table->string('approvecode', 10)->nullable()->comment('Зөвшөөрлийн код')->change();

            }
        );
        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        
    }
};
