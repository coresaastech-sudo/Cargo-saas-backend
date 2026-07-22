<?php

namespace Modules\Re\Http\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use Modules\Gp\Entities\GPInstList;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class re080018 implements WithStyles, WithEvents
{

    protected $data;
    protected $user;
    protected $inst;

    public function __construct($user, $validate)
    {
        $this->user = $user;
        $this->inst = GPInstList::find($user->instid);
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

        $sql = "WITH custaccountbase AS (
                    SELECT
                        ACNTNO,
                        INSTID,
                        NAME,
                        CUSTNAME
                    FROM (
                        -- DP Үндсэн
                        SELECT DP.ACNTNO, DP.NAME, DP.INSTID, CR.NAME AS CUSTNAME
                        FROM DP_ACCOUNT DP
                        JOIN VW_CR_CUST_LISTS CR
                            ON CR.CUSTNO = DP.CUSTNO AND CR.INSTID = DP.INSTID
                        WHERE DP.INSTID = :instid

                        UNION ALL

                        -- LN Үндсэн
                        SELECT LN.ACNTNO, LN.NAME, LN.INSTID, CR.NAME AS CUSTNAME
                        FROM LN_ACCOUNT LN
                        JOIN VW_CR_CUST_LISTS CR
                            ON CR.CUSTNO = LN.CUSTNO AND CR.INSTID = LN.INSTID
                        WHERE LN.INSTID = :instid
                        UNION ALL

                        -- IA_CT Үндсэн
                        SELECT CT.ACNTNO, CT.NAME, CT.INSTID, CR.NAME AS CUSTNAME
                        FROM IA_CT_ACCOUNT CT
                        JOIN VW_CR_CUST_LISTS CR
                            ON CR.CUSTNO = CT.CUSTNO AND CR.INSTID = CT.INSTID
                        WHERE CT.INSTID = :instid

                        UNION ALL

                        -- IA_DE Үндсэн
                        SELECT DE.ACNTNO, DEP.NAME, DE.INSTID, CR.NAME AS CUSTNAME
                        FROM IA_DE_ACCOUNT DE
                        LEFT JOIN IA_DE_ACCOUNT_TYPE DEP ON DE.INSTID = DEP.INSTID AND DE.PRODCODE = DEP.PRODCODE
                        JOIN VW_CR_CUST_LISTS CR
                            ON CR.CUSTNO = DE.LINKED_CUSTNO AND CR.INSTID = DE.INSTID
                        WHERE DE.STATUSID = 1 AND DE.INSTID = :instid
                    ) ACNT
                )
                SELECT tt.* from
                    (
                        select
                        t.jrno,
                        t.jritemno,
                        t.txndate,
                        case when t.retailacntmod='IA' then i.name else v.custname end custname,
                        t.retailacntno,
                        t.curcode,
                        t.txnamount,
                        r.avgrate as currate,
                        t.gl,
                        t.txndesc,
                        case when t.contacntmod='IA' then ic.name else vc.custname end  as contcustname,
                        t.contacntno,
                        t.conttxnamount,
                        r1.avgrate as contcurrate,
                        t.contcurcode,
                        t.contgl,
                        (t.txnamount*r.avgrate) as amountmnt,
                        (t.conttxnamount*r1.avgrate) as contamountmnt,
                        t.acntbrchno,
                        vc.name,
                        t.tellerno|| '-'|| u.name as tellername,
                        t.instid,
                        t.txncode,
                        p.name as processname
                    from tr_glretail_entry t
                        left join custaccountbase v on t.retailacntno=v.acntno and t.instid=v.instid
                        left join custaccountbase vc on t.contacntno=vc.acntno and t.instid=vc.instid
                        left join ia_account i  on t.retailacntno=i.acntno and t.instid=i.instid
                        left join ia_account ic on t.contacntno=ic.acntno and  t.instid=ic.instid
                        left join GP_inst_user u on u.instid = t.instid and u.id = t.tellerno
                        left join GP_ACTION_CODE p on  t.txncode = p.ACTION_CODE
                        JOIN GET_PERM_REPORT_BRANCH(:AC, :userid) pb ON t.acntbrchno = pb.showbrchno
                        left join tr_cur_rate_hist r on t.txndate=r.date and t.instid=r.instid and t.curcode=r.curcode
                        left join tr_cur_rate_hist r1 on t.txndate=r1.date and t.instid=r1.instid and t.contcurcode=r1.curcode
                    where t.instid=:instid and t.corr<>1 and t.txndate between :sdate and :edate and t.txndesc<>'IB Clearing'
                    and case when :allbranch = true then t.acntbrchno>'0' else t.acntbrchno = :branch end
                    and :edate::date - :sdate::date <32 and t.sign='+'

                    union all

                    select
                        t.jrno,
                        t.jritemno,
                        t.txndate,
                        case when t.retailacntmod='IA' then i.name else v.custname end custname,
                        t.retailacntno,
                        t.curcode,
                        t.txnamount,
                        r.avgrate as currate,
                        t.gl,
                        t.txndesc,
                        case when t.contacntmod='IA' then ic.name else vc.custname end  as contcustname,
                        t.contacntno,
                        t.conttxnamount,
                        r1.avgrate as contcurrate,
                        t.contcurcode,
                        t.contgl,
                        (t.txnamount*r.avgrate) as amountmnt,
                        (t.conttxnamount*r1.avgrate) as contamountmnt,
                        t.acntbrchno as brchno,
                        vc.name ,
                        t.tellerno|| '-'|| u.name as tellername,
                        t.instid,
                        t.txncode,
                        p.name as processname
                    from tr_journal t
                        left join custaccountbase v on t.retailacntno=v.acntno and t.instid=v.instid
                        left join custaccountbase vc on t.contacntno=vc.acntno and t.instid=vc.instid
                        left join ia_account i  on t.retailacntno=i.acntno and t.instid=i.instid
                        left join ia_account ic on t.contacntno=ic.acntno and t.instid=ic.instid
                        left join GP_inst_user u on u.instid = t.instid and u.id = t.tellerno
                        left join GP_ACTION_CODE p on  t.txncode = p.ACTION_CODE
                        JOIN GET_PERM_REPORT_BRANCH(:AC, :userid) pb ON t.acntbrchno = pb.showbrchno
                        left join tr_cur_rate_hist r on t.txndate=r.date and t.instid=r.instid and t.curcode=r.curcode
                        left join tr_cur_rate_hist r1 on t.txndate=r1.date and t.instid=r1.instid and t.contcurcode=r1.curcode
                    where t.instid=:instid and t.corr<>1 and t.txndate between :sdate and :edate and t.txndesc<>'IB Clearing'
                    and case when :allbranch = true then t.acntbrchno>'0' else t.acntbrchno = :branch end
                    and :edate::date - :sdate::date <32 and t.sign='+'
                    ) as tt order by tt.jrno, tt.jritemno
                    ";
        $this->data = DB::select($sql, $bindparam);
    }

    public function styles(Worksheet $sheet)
    {
        // 1-р мөр — байгууллагын нэр
        $sheet->setCellValue('A1', $this->inst->name);
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

        // 3-р мөр — "Гүйлгээний жагсаалт"
        $sheet->setCellValue('A3', "Гүйлгээний жагсаалт");
        $sheet->mergeCells('A3:V3');
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal('center');

        // 4-р мөр — heading
        $sheet->getStyle('A4:V4')->getFont()->setBold(true);
        $sheet->getStyle('A4:V4')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD9E1F2'); // цайвар хөх өнгө

        $sheet->getStyle('A4:V4')->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Автомат багана өргөнийг тохируулах
                foreach (range('A', 'V') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $headingRow = 4; // A4
                $headings = [
                    'Журнал №',
                    'Гүйлгээний огноо',
                    'Дансны нэр',
                    'Данс',
                    'Валют',
                    'Гүйлгээний дүн',
                    'Ханш',
                    'ЕД',
                    // 'Үндсэн зээл төлөлт',
                    // 'Хүү төлөлт',
                    'Гүйлгээний утга',
                    'Харьцсан дансны нэр',
                    'Харьцсан данс',
                    'Харьцсан валют',
                    'Харьцсан ханш',
                    'Харьцсан ЕД',
                    'Дүн төгрөгөөр',
                    'Харьцсан дүн төгрөгөөр',
                    'Дансны салбар',
                    'Зээлийн төрөл',
                    'Гүйлгээ хийсэн теллер',
                    'Байгууллагын ID',
                    'Гүйлгээний код',
                    'Гүйлгээний нэр',
                ];
                $sheet->fromArray($headings, null, "A{$headingRow}");

                $startRow = 5;
                $dataArray = collect($this->data)->map(function ($row) {
                    return [
                        $row->jrno,
                        $row->txndate,
                        $row->custname,
                        $row->retailacntno,
                        $row->curcode,
                        $row->txnamount,
                        $row->currate,
                        $row->gl,
                        // $row->zeel,
                        // $row->khuu,
                        $row->txndesc,
                        $row->contcustname,
                        $row->contacntno,
                        $row->contcurcode,
                        $row->contcurrate,
                        $row->contgl,
                        $row->amountmnt,
                        $row->contamountmnt,
                        $row->acntbrchno,
                        $row->name,
                        $row->tellername,
                        $row->instid,
                        $row->txncode,
                        $row->processname,
                    ];
                })->toArray();

                $sheet->fromArray($dataArray, null, "A{$startRow}");

                // Мөрийн тоо
                $dataCount = count($this->data);
                $lastDataRow = 4 + $dataCount; // Heading A4 дээр, дата A5-с эхэлдэг

                // Нягтлан ба Захирлын гарын үсгийн мөрүүд
                $accountantRow = $lastDataRow + 3;
                $directorRow   = $accountantRow + 2;

                $accountantText = sprintf(
                    'НЯГТЛАН БОДОГЧ..................................................../%s %s/',
                    $this->user->lname,
                    $this->user->name
                );

                $directorText = sprintf(
                    'ЗАХИРАЛ..................................................../%s/',
                    $this->inst->dir_name ?? ''
                );

                $sheet->setCellValue("B{$accountantRow}", $accountantText);
                $sheet->setCellValue("B{$directorRow}", $directorText);

                $sheet->mergeCells("B{$accountantRow}:G{$accountantRow}");
                $sheet->mergeCells("B{$directorRow}:G{$directorRow}");

                $sheet->getStyle("B{$accountantRow}:G{$directorRow}")->getFont()->setBold(true);
                $sheet->getStyle("B{$accountantRow}:G{$directorRow}")
                    ->getAlignment()->setHorizontal('left');
            },
        ];
    }
}
