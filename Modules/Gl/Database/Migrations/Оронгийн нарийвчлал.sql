GL
select * from tr_glretail_entry where txnamount <> ROUND(txnamount, 2) and txndate > '2025-11-01'
select * from tr_glretail_entry where txnamount <> ROUND(txnamount, 2) and created_at > '2025-11-10 17:00:00'

DP
select a.acntno, currentbal,ROUND(currentbal, 2), crdailyint, crint2acr, crint2cap, crcaptotal
from dp_account a
where
currentbal <> ROUND(currentbal, 2)
or crdailyint <> ROUND(crdailyint, 2)
or crint2cap <> ROUND(crint2cap, 2)
or crcaptotal <> ROUND(crcaptotal, 2)

-- UPDATE dp_account
SET currentbal = ROUND(currentbal, 2)
WHERE currentbal <> ROUND(currentbal, 2);
-- UPDATE dp_account
SET crdailyint = ROUND(crdailyint, 2)
WHERE crdailyint <> ROUND(crdailyint, 2);
-- UPDATE dp_account
SET crint2acr = ROUND(crint2acr, 2)
WHERE crint2acr <> ROUND(crint2acr, 2);
-- UPDATE dp_account
SET crint2cap = ROUND(crint2cap, 2)
WHERE crint2cap <> ROUND(crint2cap, 2);
-- UPDATE dp_account
SET crcaptotal = ROUND(crcaptotal, 2)
WHERE crcaptotal <> ROUND(crcaptotal, 2);

NRS

select * from ln_schd
where
ROUND(payamount, 2) <> payamount or
ROUND(intamount, 2) <> intamount or
ROUND(theorbal, 2) <> theorbal or
ROUND(intreturnamount, 2) <> intreturnamount or
ROUND(insuranceamount, 2) <> insuranceamount

-- UPDATE ln_schd
SET payamount = ROUND(payamount, 2)
WHERE payamount <> ROUND(payamount, 2);
-- UPDATE ln_schd
SET intamount = ROUND(intamount, 2)
WHERE intamount <> ROUND(intamount, 2);
-- UPDATE ln_schd
SET theorbal = ROUND(theorbal, 2)
WHERE theorbal <> ROUND(theorbal, 2);
-- UPDATE ln_schd
SET intreturnamount = ROUND(intreturnamount, 2)
WHERE intreturnamount <> ROUND(intreturnamount, 2);
-- UPDATE ln_schd
SET insuranceamount = ROUND(insuranceamount, 2)
WHERE insuranceamount <> ROUND(insuranceamount, 2);


LN
select acntno, statusid, instid, princbal, capbint, capcint, capfint,
baseint2cap, comint2cap, fineint2cap, adjbint2cap, adjcint2cap, adjfint2cap,
ctacntno, ctcomacntno, ctfineacntno, dueprinc, dueint, duecom, theorbal, nexttheorbal,
payamount
from ln_account
where
ROUND(princbal, 2) <> princbal
or ROUND(capbint, 2) <> capbint
or ROUND(capcint, 2) <> capcint
or ROUND(capfint, 2) <> capfint
or ROUND(baseint2cap, 2) <> baseint2cap
or ROUND(comint2cap, 2) <> comint2cap
or ROUND(fineint2cap, 2) <> fineint2cap
or ROUND(adjbint2cap, 2) <> adjbint2cap
or ROUND(adjcint2cap, 2) <> adjcint2cap
or ROUND(adjfint2cap, 2) <> adjfint2cap
or ROUND(dueprinc, 2) <> dueprinc
or ROUND(dueint, 2) <> dueint
or ROUND(duecom, 2) <> duecom
or ROUND(ctacntno, 2) <> ctacntno
or ROUND(ctcomacntno, 2) <> ctcomacntno
or ROUND(ctfineacntno, 2) <> ctfineacntno
or ROUND(theorbal, 2) <> theorbal
or ROUND(nexttheorbal, 2) <> nexttheorbal
or ROUND(payamount, 2) <> payamount



--UPDATE ln_account
SET payamount = ROUND(payamount, 2)
WHERE payamount <> ROUND(payamount, 2);
--UPDATE ln_account
SET nexttheorbal = ROUND(nexttheorbal, 2)
WHERE nexttheorbal <> ROUND(nexttheorbal, 2);
--UPDATE ln_account
SET theorbal = ROUND(theorbal, 2)
WHERE theorbal <> ROUND(theorbal, 2);

--UPDATE ln_account
SET ctfineacntno = ROUND(ctfineacntno, 2)
WHERE ctfineacntno <> ROUND(ctfineacntno, 2);

--UPDATE ln_account
SET ctcomacntno = ROUND(ctcomacntno, 2)
WHERE ctcomacntno <> ROUND(ctcomacntno, 2);

--UPDATE ln_account
SET ctacntno = ROUND(ctacntno, 2)
WHERE ctacntno <> ROUND(ctacntno, 2);


--UPDATE ln_account
SET adjfint2cap = ROUND(adjfint2cap, 2)
WHERE adjfint2cap <> ROUND(adjfint2cap, 2);

--UPDATE ln_account
SET adjcint2cap = ROUND(adjcint2cap, 2)
WHERE adjcint2cap <> ROUND(adjcint2cap, 2);

--UPDATE ln_account
SET adjbint2cap = ROUND(adjbint2cap, 2)
WHERE adjbint2cap <> ROUND(adjbint2cap, 2);

--UPDATE ln_account
SET fineint2cap = ROUND(fineint2cap, 2)
WHERE fineint2cap <> ROUND(fineint2cap, 2);

--UPDATE ln_account
SET comint2cap = ROUND(comint2cap, 2)
WHERE comint2cap <> ROUND(comint2cap, 2);

--UPDATE ln_account
SET baseint2cap = ROUND(baseint2cap, 2)
WHERE baseint2cap <> ROUND(baseint2cap, 2);

--UPDATE ln_account
SET capfint = ROUND(capfint, 2)
WHERE capfint <> ROUND(capfint, 2);

--UPDATE ln_account
SET capcint = ROUND(capcint, 2)
WHERE capcint <> ROUND(capcint, 2);

--UPDATE ln_account
SET capbint = ROUND(capbint, 2)
WHERE capbint <> ROUND(capbint, 2);

--UPDATE ln_account
SET princbal = ROUND(princbal, 2)
WHERE princbal <> ROUND(princbal, 2);

--UPDATE ln_account
SET dueprinc = ROUND(dueprinc, 2)
WHERE dueprinc <> ROUND(dueprinc, 2);

--UPDATE ln_account
SET dueint = ROUND(dueint, 2)
WHERE dueint <> ROUND(dueint, 2);

--UPDATE ln_account
SET duecom = ROUND(duecom, 2)
WHERE duecom <> ROUND(duecom, 2);

IA

select a.acntno, currentbal,ROUND(currentbal, 2)
from ia_account a
where
currentbal <> ROUND(currentbal, 2)


-- UPDATE ia_account
SET currentbal = ROUND(currentbal, 2)
WHERE currentbal <> ROUND(currentbal, 2);

CT

select a.acntno, currentbal,ROUND(currentbal, 2)
from ia_ct_account a
where
currentbal <> ROUND(currentbal, 2)


-- UPDATE ia_ct_account
SET currentbal = ROUND(currentbal, 2)
WHERE currentbal <> ROUND(currentbal, 2);

CA

select sbal, bal, ctbal, dtbal
from ca_cash_bal
where
sbal <> ROUND(sbal, 2)
or bal <> ROUND(bal, 2)
or ctbal <> ROUND(ctbal, 2)
or dtbal <> ROUND(dtbal, 2)


-- UPDATE ca_cash_bal
SET ctbal = ROUND(ctbal, 2)
WHERE  ctbal <> ROUND(ctbal, 2);

--UPDATE ca_cash_bal
SET dtbal = ROUND(dtbal, 2)
WHERE
dtbal <> ROUND(dtbal, 2);

--UPDATE ca_cash_bal
SET bal = ROUND(bal, 2)
WHERE
bal <> ROUND(bal, 2);
