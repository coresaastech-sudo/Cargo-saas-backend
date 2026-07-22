<?php

namespace Modules\Re\Http\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GPInstList;

class re080299
{

    public function generateReport($user, $validate)
    {
        $user = $user;
        $inst = GPInstList::find($user->instid);
        $bindparam = [
            'instid' => $user->instid,
            'userid' => $user->id,
            'AC' => $validate['ACTION_CODE'],
        ];

        foreach ($validate['inputs'] as $key => $value) {
            if (isset($value['value']) && isset($value['input'])) {
                $bindparam[$value['input']] = $value['value'];
            }
        }

        $sql = "SELECT
                    row_number() over (order by t.txndate, l.acntno, l.prodcode,l.brchno) cola_day,
                    l.custno,
                    l.custno as custno_day,
                    l.acntno colb,
                    l.acntno colb_day,
                    c.lname colc,
                    c.name cold,
                    c.loancount::INT,
                    c.loancount::INT cole_day,
                    ca.itemvalue as crgroup,
                    ca1.itemvalue as crno,
                    l.intrate::FLOAT colf,
                    l.advdate colg,
                    l.enddate,
                    l.termlen,
                    l.termlen as termlen_day,
                    case when l.termbasis = 'D' then 'өдөр'   when l.termbasis = 'M' then 'сар'  when l.termbasis = 'Y' then 'жил'  else ''  end as termbasis ,
                    t.txndate,
                    t.txnamount::FLOAT as advamount,
                    pm.name || ' - ' || ps.name as purpname,
                    l.purpcode||'-'||l.subpurpcode as purp,
                case when	l.statusid = 0 then 'Хаагдсан'
                        when	l.statusid = 1 then 'Зөвшөөрөгдсөн'
                        when	l.statusid = 2 then 'Зогсоосон'
                        when	l.statusid = 3 then 'Хүү зогсоосон'
                        when	l.statusid = 4 then 'Олгогдсон'
                        when	l.statusid = 5 then 'Шинэ'
                        when	l.statusid = 8 then 'Худалдсан'
                        when	l.statusid = 9 then 'Худалд хаагдсан'
                        else 'Тодорхойгүй'
                end as status,
                    l.instid,
                    l.sellermanager || ' - ' || u1.name as createdname,
                    l.created_by || ' - ' || u.name managername,
                    l.prodcode || ' - ' || p.name prodname,
                    l.brchno || ' - ' || 	br.name branchname,
                    gt.name as loantype,
                    gz.name as lnsubtype,
                    gm.name || ' - ' || gs.name as greenpurpname,
                    case when l.catcode=lc.value then lc.name else '' end catcode,
                    lm2.morno,
                    lm2.regno,
                    lm2.certno,
                    lm2.costamount::FLOAT as costamount,
                    lm2.insurance,
                    lm2.insuranceexdate

                from ln_txn t
                    left join ln_account l on t.instid=l.instid and t.acntno=l.acntno
                    join vw_cr_cust_lists c on c.instid = l.instid and l.custno = c.custno
                    left join ln_account_type p on p.instid = l.instid and p.prodcode = l.prodcode
                    left join GP_inst_branch br on br.instid = l.instid and br.brchno = l.brchno
                    join GP_inst_user u on u.instid = l.instid and u.id = l.created_by
                    left join GP_inst_user u1 on u1.instid = l.instid and u1.id = l.sellermanager
                    left join GP_const pm on pm.parent_code = 'loan_industry' and pm.value = l.purpcode
                    left join GP_const ps on ps.parent_code = pm.code and ps.value =  l.subpurpcode
                    left join GP_const gt on l.loantype=gt.value and gt.parent_code = 'loan_type_ind'
                    left join GP_const gz on l.lnsubtype=gz.value and gz.parent_code = 'lntype_B'
                    left join GP_const gm on gm.parent_code = 'greenpurp' and gm.value = l.greenpurpcode
                    left join GP_const gs on gs.parent_code = gm.code and gs.value =  l.greensubpurpcode
                    left join GP_const lc on l.catcode=lc.value and lc.parent_code = 'ln_acnt_cat'

                    left join cr_cust_add ca on ca.custid=c.id and c.instid=ca.instid and ca.keyfield=294
                    left join cr_cust_add ca1 on ca1.custid=c.id and c.instid=ca1.instid and ca1.keyfield=295

                    left join ( select lm.acntno, lm.instid, sum(m.costamount) as costamount,
                        STRING_AGG(lm.morno, ',' ) as morno,
                        STRING_AGG(m.regno, ',' ) as regno,
                        STRING_AGG(m.certno, ',' ) as certno,
                        STRING_AGG(ma1.itemvalue, ',' ) as insurance,
                        STRING_AGG(ma2.itemvalue, ',' ) as insuranceexdate
                    from ln_account_mor lm
                        left join ln_mor m on lm.instid=m.instid and lm.morno=m.morno
                        left join GP_inst_add_field f1 on lm.instid=f1.instid and f1.code ='ln_auto_insurance'
                        left join GP_inst_add_field f2 on lm.instid=f2.instid and f2.code ='ln_auto_insurance_exdate'
                        left join ln_mor_add ma1 on lm.instid=ma1.instid and m.instid=ma1.instid and ma1.keyfield=f1.id and lm.morno=ma1.morno
                        left join ln_mor_add ma2 on lm.instid=ma2.instid and m.instid=ma2.instid and ma2.keyfield=f2.id and lm.morno=ma2.morno
                    where lm.statusid=1 and lm.instid = :instid
                    group by lm.acntno, lm.instid
                    ) as lm2 on l.instid=lm2.instid and l.acntno=lm2.acntno

                    JOIN GET_PERM_REPORT_BRANCH(:AC, :userid) pb ON l.brchno = pb.showbrchno
                where l.instid = :instid and t.txndate between :startdate and :enddate
                and t.txncode in( 'ln902020','ln902021')
                and t.corr<>1
                and case when :allbranch = true then l.brchno>'0' else l.brchno = :branch  end
                order by  t.txndate, l.acntno, l.prodcode";

        $datas = DB::select($sql, $bindparam);

        $html = view("tr::reports.re080299", [
            'rows' => $datas,
            'fromDate' => $bindparam['startdate'],
            'toDate' => $bindparam['enddate'],
            'reportUser' => "$user->lname $user->name",
            'inst' => $inst
        ])->render();
        header('Content-type: application/pdf');
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('tempDir', storage_path());
        $options->set('chroot', __DIR__);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = "Зээл олголтын мэдээ {$bindparam['startdate']} - {$bindparam['enddate']}.pdf";

        return [
            'type' => 'pdf',
            'source' => base64_encode($dompdf->output()),
            'exporttype' => 4,
            'filename' => $filename,
        ];
    }
}
