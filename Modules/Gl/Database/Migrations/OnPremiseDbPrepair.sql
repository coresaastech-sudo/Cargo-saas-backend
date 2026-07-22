truncate table ad_auto_job;
truncate table ad_batch_registration_details;
truncate table ad_batch_registrations;
truncate table ad_batch_txn;
truncate table ad_batch_txn_detail;
truncate table ad_cgw_transaction;
truncate table ad_cgw_txn_desc_combination;
truncate table ad_corporate_gateway;
truncate table ad_credit_info_buero;
truncate table ad_credit_info_buero_action;
truncate table ad_credit_info_buero_detail;
truncate table ad_credit_info_buero_hist;
truncate table ad_ebarimt;
truncate table ad_ebarimt_ACTION_CODE;
truncate table ad_eod_log;
truncate table ad_eod_log_detail;
truncate table ad_hide;
truncate table ad_login_activity_log;
truncate table ad_login_confirm_device;
truncate table ad_notifications;
truncate table ad_res_account_bal;
truncate table ad_sent_notification;
truncate table ad_zms_inquiry;

delete from ad_perm_report where instid <> 1;

truncate table ap_acnt_cd;
truncate table ap_acnt_dp;
truncate table ap_acnt_int;
truncate table ap_acnt_ln;
truncate table ap_acnt_schedules;
truncate table ap_acnt_statements;
truncate table ap_contract_sign_image;
truncate table ap_cust;
truncate table ap_cust_bank_account;
truncate table ap_cust_bank_token;
truncate table ap_cust_contracts;
truncate table ap_cust_inquiry;
truncate table ap_cust_user;
truncate table ap_dan_response;
truncate table ap_inst_cust_user_link;
truncate table ap_inst_stop_service;
truncate table ap_negdi;
truncate table ap_qpay;
truncate table ap_txn_journal;
truncate table ap_user_image;
truncate table ca_cash_bal;
truncate table ca_cash_list;
truncate table cd_contract;
truncate table cd_cust_ind;
truncate table cd_token;
truncate table cd_tran;

truncate table cr_cust_add;
truncate table cr_cust_address;
truncate table cr_cust_bank_account;
truncate table cr_cust_contact;
truncate table cr_cust_doc;
truncate table cr_cust_image;
truncate table cr_cust_ind;
truncate table cr_cust_msg;
truncate table cr_cust_notification_config;
truncate table cr_cust_notifications;
truncate table cr_cust_org;
truncate table cr_cust_relation;
truncate table cr_cust_salarydays;
truncate table cr_cust_sale_asset;
truncate table cr_cust_secret;
truncate table cr_cust_shareholder;
truncate table cr_cust_sign;
truncate table cr_cust_sign_acnt;
truncate table cr_cust_sign_image;

truncate table dp_account;
truncate table dp_account_add;
truncate table dp_account_bal;
truncate table dp_account_bal_txn;
truncate table dp_account_charge;
truncate table dp_account_cust;
truncate table dp_account_hist;
truncate table dp_account_hold;
truncate table dp_account_insurance;
truncate table dp_account_int_rate;
truncate table dp_account_mor;
truncate table dp_account_s_order;
truncate table dp_account_sweep;
truncate table dp_account_type;
truncate table dp_account_type_add;
truncate table dp_account_type_cls;
truncate table dp_account_type_fee;
truncate table dp_account_type_int_rate;
truncate table dp_account_type_seg;
truncate table dp_account_type_temp;
truncate table dp_hold_txn;
truncate table dp_inv_account;
truncate table dp_inv_account_loans;
truncate table dp_inv_package;
truncate table dp_inv_schd;
truncate table dp_inv_schd_hist;
truncate table dp_roll_temp;
truncate table dp_txn;

truncate table failed_jobs;
truncate table gl_account;
truncate table gl_account_class;
truncate table gl_balance;
truncate table gl_balance_hist;
truncate table gl_chart;
truncate table gl_daily_bal;
truncate table gl_daily_bal_hist;
truncate table gl_report_conf_column;
truncate table gl_report_conf_detail;
truncate table gl_report_conf_list;
truncate table gl_transaction;

delete from GP_const where instid <> 1 or statusid <> 1;
delete from GP_inst_branch where instid <> 1  or statusid <> 1;
delete from GP_inst_eod_steps where instid <> 1  or statusid <> 1;
delete from GP_inst_gp where instid <> 1;
delete from GP_inst_list where id <> 1  or statusid <> 1;
delete from GP_inst_perms where instid <> 1  or statusid <> 1;
delete from GP_inst_role where instid <> 1  or statusid <> 1;
delete from GP_inst_role_perms where roleid <> 1  or statusid <> 1;
delete from GP_inst_seq where instid <> 1;
delete from GP_inst_user where instid <> 1  or statusid <> 1;
delete from GP_inst_user_roles where instid <> 1  or statusid <> 1;
delete from GP_provider_conf where instid <> 1  or statusid <> 1;

truncate table GP_app_list;
truncate table GP_audit_log;
truncate table GP_audit_log_detail;
truncate table GP_conn_conf;
truncate table GP_db_backup_log;
truncate table GP_files;
truncate table GP_inst_add;
truncate table GP_inst_add_field;
truncate table GP_inst_contact;
truncate table GP_inst_cur;
truncate table GP_inst_cur_pair;
truncate table GP_inst_cur_rate;
truncate table GP_inst_cur_rate_hist;
truncate table GP_inst_doc_temp;
truncate table GP_inst_doc_temp_form_input;
truncate table GP_inst_doc_temp_ACTION_CODE;
truncate table GP_inst_doc_temp_var;
truncate table GP_inst_fee;
truncate table GP_inst_fee_cur;
truncate table GP_inst_fee_rate;
truncate table GP_inst_fee_source;
truncate table GP_inst_freq_fee_job;
truncate table GP_inst_invoice;
truncate table GP_inst_qual;
truncate table GP_inst_susp;
truncate table GP_inst_tariff;
truncate table GP_inst_txn_fee;
truncate table GP_inst_txn_type;
truncate table GP_job_infos;
truncate table GP_photos;
truncate table GP_user_access_tokens;
truncate table GP_user_act_list;
truncate table GP_user_passhist;


truncate table ia_account;
truncate table ia_account_hist;
truncate table ia_account_type;
truncate table ia_ct_account;
truncate table ia_ct_account_add;
truncate table ia_ct_account_hist;
truncate table ia_ct_account_type;
truncate table ia_ct_account_type_add;
truncate table ia_ct_txn;
truncate table ia_de_account;
truncate table ia_de_account_type;
truncate table ia_de_schd;
truncate table ia_de_txn;
truncate table ia_de_type_prodlink;
truncate table ia_position;
truncate table ia_rec_pay;
truncate table ia_rec_pay_txn;
truncate table ia_txn;

truncate table jobs;

truncate table ln_account;
truncate table ln_account_add;
truncate table ln_account_bal_cls;
truncate table ln_account_cust;
truncate table ln_account_due;
truncate table ln_account_hist;
truncate table ln_account_int_rate;
truncate table ln_account_limit_schd;
truncate table ln_account_log;
truncate table ln_account_mor;
truncate table ln_account_type;
truncate table ln_account_type_add;
truncate table ln_account_type_appadd;
truncate table ln_account_type_cls;
truncate table ln_account_type_fee;
truncate table ln_account_type_int_rate;
truncate table ln_app;
truncate table ln_app_add;
truncate table ln_app_cust;
truncate table ln_app_mor;
truncate table ln_app_schd;
truncate table ln_app_schd_hist;
truncate table ln_due_txn;
truncate table ln_mor;
truncate table ln_mor_add;
truncate table ln_mor_hist;
truncate table ln_mor_owner;
truncate table ln_mor_type;
truncate table ln_mor_type_add;
truncate table ln_sale_asset_detail;
truncate table ln_sale_asset_package;
truncate table ln_sale_asset_send_txn;
truncate table ln_schd;
truncate table ln_schd_hist;
truncate table ln_txn;

truncate table log_changes;
truncate table log_errors;
truncate table log_requests;


truncate table tr_cur_rate_hist;
truncate table tr_glretail_bal;
truncate table tr_glretail_entry;
truncate table tr_journal;

truncate table websockets_statistics_entries;


/**
Бүх модул эрх
'gp000000',
'cr000000',
'ia000000',
'dp000000',
'ln000000',
'ca000000',
're000000',
'gl000000',
'tr000000',
'lo000100',
'ap000000',
'cd000000',
'ad000000'
**/
INSERT INTO GP_inst_role_perms (roleid, AC, isadmin, statusid, created_by)
SELECT r.id,
       'gp000000'      AS AC,
       0               AS isadmin,   -- эсвэл FALSE
       1               AS statusid,
       245     AS created_by -- өөрийн user ID-г дамжуул
FROM GP_inst_role r
WHERE r.statusid = 1
  AND NOT EXISTS (
    SELECT 1
    FROM GP_inst_role_perms p
    WHERE p.roleid = r.id
      AND p.AC = 'gp000000'
)
RETURNING *;
