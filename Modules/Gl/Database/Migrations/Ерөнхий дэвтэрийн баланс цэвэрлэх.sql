Ерөнхий дэвтэрийн баланс цэвэрлэх
select * from gl_transaction where instid = 22;
--delete from gl_transaction where instid = 22;
select * from gl_balance where instid = 22;
--update gl_balance set dt01 = 0, ct01 = 0, dt02 = 0, ct02 = 0, dt03 = 0, ct03 = 0 where instid = 22;
select * from gl_daily_bal where instid = 22;
--delete from gl_daily_bal where instid = 22;
select * from gl_daily_bal_hist where instid = 22;
--delete from gl_daily_bal_hist where instid = 22;
--update gl_daily_bal set obal = 0, dt02 = 0, ct02 = 0, dt03 = 0, ct03 = 0, dt01 = 0, ct01 = 0, dt31 = 0, ct31 = 0 where instid = 22;
select * from GP_inst_cur_rate_hist where instid = 22;
--delete from GP_inst_cur_rate_hist where instid = 22;


Суурийн баланс цэвэрлэх

SELECT * FROM tr_glretail_bal where instid = 22;
--delete from tr_glretail_bal where instid = 22;
SELECT * FROM tr_glretail_entry where instid = 22;
--delete from tr_glretail_entry where instid = 22;
SELECT * FROM tr_journal where instid = 22;
--delete from tr_journal where instid = 22;
SELECT * FROM tr_cur_rate_hist where instid = 22;
--delete from tr_cur_rate_hist where instid = 22;
SELECT * FROM ln_txn where instid = 22;
--delete from ln_txn where instid = 22;
SELECT * FROM dp_txn where instid = 22;
--delete from dp_txn where instid = 22;
SELECT * FROM ia_txn where instid = 22;
--delete from ia_txn where instid = 22;
SELECT * FROM ia_ct_txn where instid = 22;
--delete from ia_ct_txn where instid = 22;
SELECT * FROM dp_hold_txn where instid = 22;
--delete from dp_hold_txn where instid = 22;
SELECT * FROM ln_due_txn where instid = 22;
--delete from ln_due_txn where instid = 22;

Данснууд устгах
SELECT * FROM ln_account where instid = 22;
--delete FROM ln_account where instid = 22;
SELECT * FROM ln_account_due where instid = 22;
--delete FROM ln_account_due where instid = 22;

SELECT * FROM dp_account where instid = 22;
--delete FROM dp_account where instid = 22;
SELECT * FROM dp_account_hold where instid = 22;
--delete FROM dp_account_hold where instid = 22;

SELECT * FROM ia_account where instid = 22;
--delete FROM ia_account where instid = 22;
SELECT * FROM ia_ct_account where instid = 22;
--delete FROM ia_ct_account where instid = 22;
SELECT * FROM ia_ct_account_hist where instid = 22;
--delete FROM ia_ct_account_hist where instid = 22;
select * from ca_cash_bal  where instid = 22;
--delete from ca_cash_bal  where instid = 22;

Дансны үлд, хур хүү 0 болгох
SELECT * FROM ln_account where instid = 22;
--update ln_account set
        princbal = 0,
        capbint = 0,
        capcint = 0,
        capfint = 0,
        paidlon = 0,
        paidint = 0,
        comamount = 0,
        dueamount = 0,
        ctacntno = 0,
        ctcomacntno = 0,
        ctfineacntno = 0,
        ctlineacntno = 0,
        cectacntno = 0,
        ceinvintrate = 0,
        baseintdaily = 0,
        comintdaily = 0,
        fineintdaily = 0,
        baseint2cap = 0,
        comint2cap = 0,
        fineint2cap = 0,
        adjbint2cap = 0,
        adjcint2cap = 0,
        adjfint2cap = 0,
        tmp_princbal = 0,
        tmp_acrbint = 0,
        tmp_capbint = 0,
        tmp_acrcint = 0,
        tmp_capcint = 0,
        tmp_acrfint = 0,
        tmp_capfint = 0,
        dueprinc = 0,
        dueint = 0,
        duecom = 0,
        linebal = 0,
        linebasebal = 0,
        baseroundint = 0,
        clscode = 1,
        tmp_clscode = 1,
        statusid = 4,
        tmp_status = 4,
        where instid = 22;

SELECT * FROM dp_account where instid = 22;
--update dp_account set
        currentbal =0,
        crdailyint =0,
        crint2acr =0,
        crint2cap =0,
        cradjint =0,
        crcaptotal2 =0,
        tmp_bal = 0,
        tmp_crint2cap = 0,
        tmp_crintrate = 0,
        tmp_crcaptotal2
where instid = 22;
SELECT * FROM ia_account where instid = 22;
--update ia_account set
        currentbal = 0,
        tmp_currentbal = 0,
        risk = 0,
where instid = 22;

SELECT * FROM ia_ct_account where instid = 22;
--update ia_ct_account set
        currentbal = 0,
        applicationamount = 0,
        approvalamount = 0,
        currentcount = 0,
        capint = 0,
        tmp_currentbal = 0,
        tmp_currentcount = 0,
        tmp_capint = 0
where instid = 22;
select * from ca_cash_bal  where instid = 22;
--update ca_cash_bal set
        sbal = 0,
        bal = 0,
        dtbal = 0,
        ctbal = 0
where instid = 22;


Худалдсан зээлийг буцаасан
Select * from ln_sale_asset_detail where instid = 22 and statusid = 2
--Update ln_sale_asset_detail set statusid = 1 where instid = 22 and statusid = 2
Select * from ln_account where instid = 22 and statusid = 8 ;
--Update ln_account set statusid = 4 where instid = 22 and statusid = 8;

Өдөр тохируулах
select * from GP_inst_seq where instid = 22;



select * from ad_res_account_bal where instid = 22;
--delete from ad_res_account_bal where instid = 22;
select * from ln_schd_hist where instid = 22;
--delete from ln_schd_hist where instid = 22;
select * from ia_account_hist where instid = 22;
--delete from ia_account_hist where instid = 22;
select * from ia_position  where instid = 22;
--delete from ia_position  where instid = 22;
select * from dp_account_hist  where instid = 22;
--delete from dp_account_hist  where instid = 22;
select * from ln_account_hist  where instid = 22;
--delete from ln_account_hist  where instid = 22;
--Барьцаа хөрөнгийн тэнцэлийн гадуурх үнэлгээ цэвэрлэх ёстой!!! ctacntno = 0,  obamount = 0,   obpercent = 0
select * from ln_mor  where instid = 22;
--delete from ln_mor  where instid = 22;
select * from ln_mor_hist  where instid = 22;
--delete from ln_mor_hist  where instid = 22;
select * from ad_eod_log  where instid = 22;
--delete from ad_eod_log  where instid = 22;
select * from ad_batch_txn  where instid = 22;
--delete from ad_batch_txn  where instid = 22;
select * from ad_batch_txn_detail  where instid = 22;
--delete from ad_batch_txn_detail  where instid = 22;
select * from ad_cgw_transaction  where instid = 22;
--delete from ad_cgw_transaction  where instid = 22;
select * from ad_corporate_gateway  where instid = 22;
--delete from ad_corporate_gateway  where instid = 22;
select * from dp_account_hold  where instid = 22;
--delete from dp_account_hold  where instid = 22;
select * from dp_hold_txn  where instid = 22;
--delete from dp_hold_txn  where instid = 22;
select * from ln_account_due  where instid = 22;
--delete from ln_account_due  where instid = 22;
select * from ln_due_txn  where instid = 22;
--delete from ln_due_txn  where instid = 22;
select * from ln_account_mor  where instid = 22;
--delete from ln_account_mor  where instid = 22;
select * from dp_roll_temp  where instid = 22;
--delete from dp_roll_temp  where instid = 22;
select * from ad_ebarimt  where instid = 22;
select * from ad_credit_info_buero  where instid = 22;
select * from ad_batch_registrations  where instid = 22;
--delete from ad_batch_registrations  where instid = 22;
select * from ad_batch_registration_details  where instid = 22;
--delete from ad_batch_registration_details  where instid = 22;
select * from ad_batch_txn  where instid = 22;
--delete from ad_batch_txn  where instid = 22;
select * from ad_batch_txn_detail  where instid = 22;
--delete from ad_batch_txn_detail  where instid = 22;
select * from GP_files  where instid = 22;
--delete from GP_files  where instid = 22;
select * from gl_balance_hist  where instid = 22;
--delete from gl_balance_hist  where instid = 22;
select * from GP_audit_log  where instid = 22;
--delete from GP_audit_log  where instid = 22;
select * from ln_schd  where instid = 22;
--delete from ln_schd  where instid = 22;
select * from ln_schd_hist  where instid = 22;
--delete from ln_schd_hist  where instid = 22;
select * from dp_account_int_rate  where instid = 22;
--delete from dp_account_int_rate  where instid = 22;

DO $$
DECLARE
    rec record;
    tablename text;
    query_string text;
	result_row record;
BEGIN
    FOR rec IN
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'mc' AND table_type = 'BASE TABLE' AND table_name not in (
			'GP_inst_list',
			'GP_api_ACTION_CODE',
			'ap_cust_bank_account',
			'ap_cust_user',
			'ad_login_activity_log',
			'ad_login_confirm_device',
			'GP_inst_role_perms',
			'GP_photos',
			'ap_user_image',
			'log_changes',
			'log_requests',
			'GP_ACTION_CODE',
			'GP_response_msg',
			'GP_module_list',
			'migrations',
			'jobs',
			'failed_jobs',
			'GP_dic_mains',
			'log_errors',
			'GP_user_passhist',
			'GP_user_access_tokens',
			'GP_dic_passpolicy',
			'websockets_statistics_entries',
			'login_activity_log',
			'login_confirm_device',
            'GP_job_infos',
			'ap_dan_response',
			'cr_cust_sign_image',
            'ap_cust_bank_token',
            'GP_audit_log_detail',
            'ap_cust_inquiry'
		)
    LOOP
        tablename := rec.table_name;
        query_string := 'SELECT * FROM ' || quote_ident(tablename) || ' WHERE instid = 22;';

        -- Execute the dynamic query
        FOR result_row IN EXECUTE query_string
        LOOP
            -- Print each column value of the current row
			RAISE NOTICE '%', tablename;
            RAISE NOTICE '%', result_row;
        END LOOP;
    END LOOP;
END $$;
--Хуваарь бурууг засах
select a.instid , a.acntno, a.nextpayday, a.enddate,
(select min(n.payday) from ln_schd n where n.instid = a.instid and a.acntno = n.acntno and s.seqno ::date <= n.payday) as newdate,
s.seqno ::date as sysdate
from ln_account a
left join GP_inst_seq s on s.instid=a.instid and s.seqid ='SYSDATE'
where a.statusid > 0 and a.nextpayday < s.seqno ::date and a.instid != 2 and a.enddate > s.seqno ::date;

--update ln_account set nextpayday = '2024-05-05' where acntno = 	'100002126' and instid = 22;

--Хуваарь дараагын онолын үлдэгдэл буруу
select n1.theorbal as nrsnextthoerbal, a.acntno
 --a.instid, a.prodcode, a.theorbal, a.nexttheorbal,
--n.theorbal as nrsthoerbal,  a.nextpayday, n1.payday
from ln_account a
left join GP_inst_seq s on s.instid=a.instid and s.seqid ='SYSDATE'
left join
 (
	 select *
		from ln_schd
		where (acntno, payday, instid) in (
			select acntno, max(payday), instid
			from ln_schd n
			where payday <= (
			select s.seqno::date from  GP_inst_seq s where s.instid=n.instid and s.seqid ='SYSDATE'
			) group by acntno, instid
		)
 ) n
 on n.instid = a.instid
 	and n.acntno = a.acntno
left join
 (
	 select *
		from ln_schd
		where (acntno, payday, instid) in (
			select acntno, min(payday), instid
			from ln_schd n
			where payday >= (
			select s.seqno::date from  GP_inst_seq s where s.instid=n.instid and s.seqid ='SYSDATE'
			) group by acntno, instid
		)
 ) n1
 on n1.instid = a.instid
 	and n1.acntno = a.acntno
where a.theorbal<>n.theorbal and a.instid != 2 and a.instid = 22 and a.nexttheorbal>n.theorbal
and a.enddate > (
			select s.seqno::date from  GP_inst_seq s where s.instid=a.instid and s.seqid ='SYSDATE'
			);

--update ln_account set nexttheorbal=13412057.02000000 where acntno =	'100001893' and instid = 22;


--Бутархай оронг .00 болгох


Дансны үлд, хур хүү 0 болгох
SELECT  princbal,
        capbint,
        capcint,
        capfint,
        paidlon,
        paidint,
        comamount,
        dueamount,
        ctacntno,
        ctcomacntno,
        ctfineacntno,
        ctlineacntno,
        cectacntno,
        ceinvintrate,
        baseintdaily,
        comintdaily,
        fineintdaily,
        baseint2cap,
        comint2cap,
        fineint2cap,
        adjbint2cap,
        adjcint2cap,
        adjfint2cap,
        tmp_princbal,
        tmp_acrbint,
        tmp_capbint,
        tmp_acrcint,
        tmp_capcint,
        tmp_acrfint,
        tmp_capfint,
        dueprinc,
        dueint,
        duecom,
        linebal,
        linebasebal,
        baseroundint,
        comroundint,
        fineroundint
         FROM ln_account where instid = 22;
--update ln_account set
        princbal = round(princbal, 2),
        capbint = round(capbint, 2),
        capcint = round(capcint, 2),
        capfint = round(capfint, 2),
        paidlon = round(paidlon, 2),
        paidint = round(paidint, 2),
        comamount = round(comamount, 2),
        dueamount = round(dueamount, 2),
        ctacntno = round(ctacntno, 2),
        ctcomacntno = round(ctcomacntno, 2),
        ctfineacntno = round(ctfineacntno, 2),
        ctlineacntno = round(ctlineacntno, 2),
        cectacntno = round(cectacntno, 2),
        ceinvintrate = round(ceinvintrate, 2),
        baseintdaily = round(baseintdaily, 2),
        comintdaily = round(comintdaily, 2),
        fineintdaily = round(fineintdaily, 2),
        baseint2cap = round(baseint2cap, 2),
        comint2cap = round(comint2cap, 2),
        fineint2cap = round(fineint2cap, 2),
        adjbint2cap = round(adjbint2cap, 2),
        adjcint2cap = round(adjcint2cap, 2),
        adjfint2cap = round(adjfint2cap, 2),
        tmp_princbal = round(tmp_princbal, 2),
        tmp_acrbint = round(tmp_acrbint, 2),
        tmp_capbint = round(tmp_capbint, 2),
        tmp_acrcint = round(tmp_acrcint, 2),
        tmp_capcint = round(tmp_capcint, 2),
        tmp_acrfint = round(tmp_acrfint, 2),
        tmp_capfint = round(tmp_capfint, 2),
        dueprinc = round(dueprinc, 2),
        dueint = round(dueint, 2),
        duecom = round(duecom, 2),
        linebal = round(linebal, 2),
        linebasebal = round(linebasebal, 2)
        where instid = 22;

SELECT currentbal,
        crdailyint,
        crint2acr,
        crint2cap,
        cradjint,
        crcaptotal2,
        tmp_bal,
        tmp_crint2cap,
        tmp_crintrate,
        tmp_crcaptotal2
        FROM dp_account where instid = 22;
--update dp_account set
        currentbal = round(currentbal, 2),
        crdailyint = round(crdailyint, 2),
        crint2acr = round(crint2acr, 2),
        crint2cap = round(crint2cap, 2),
        cradjint = round(cradjint, 2),
        crcaptotal2 = round(crcaptotal2, 2),
        tmp_bal = round(tmp_bal, 2),
        tmp_crint2cap = round(tmp_crint2cap, 2),
        tmp_crintrate = round(tmp_crintrate, 2),
        tmp_crcaptotal2 = round(tmp_crcaptotal2, 2),
        crroundint = 0
where instid = 22;
SELECT * FROM ia_account where instid = 22;
--update ia_account set
        currentbal = 0,
        tmp_currentbal = 0,
        risk = 0,
where instid = 22;

SELECT currentbal,
        applicationamount,
        approvalamount,
        currentcount,
        capint,
        tmp_currentbal,
        tmp_currentcount,
        tmp_capint FROM ia_ct_account where instid = 22;
--update ia_ct_account set
        currentbal = round(currentbal, 2),
        applicationamount = round(applicationamount, 2),
        approvalamount = round(approvalamount, 2),
        currentcount = round(currentcount, 2),
        capint = round(capint, 2),
        tmp_currentbal = round(tmp_currentbal, 2),
        tmp_currentcount = round(tmp_currentcount, 2),
        tmp_capint = round(tmp_capint, 2)
where instid = 22;

select  sbal,
        bal,
        dtbal,
        ctbal
 from ca_cash_bal  where --instid = 22;
--update ca_cash_bal set
        sbal = round(sbal, 2),
        bal = round(bal, 2),
        dtbal = round(dtbal, 2),
        ctbal = round(ctbal, 2)
where instid = 22;
