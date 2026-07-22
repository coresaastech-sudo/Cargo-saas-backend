WITH RECURSIVE
report_hierarchy AS (
    SELECT
        c1.*,
        c1.conf_detail_id AS root_conf_detail_id
    FROM gl_report_conf_column c1
    WHERE c1.statusid = 1
      AND c1.instid = :instid

    UNION ALL

    SELECT
        c2.*,
        rh.root_conf_detail_id
    FROM gl_report_conf_column c2
    JOIN report_hierarchy rh
      ON rh.instid = c2.instid
     AND c2.statusid = rh.statusid
     AND c2.columnidx = rh.columnidx
     AND rh.conf_detail_id <> c2.conf_detail_id
     AND rh.acntno ~ '^[0-9]+$'
     AND rh.acntno::int = c2.conf_detail_id
    WHERE c2.statusid = 1
      AND c2.instid = :instid
),
gl_report_conf_column_2 AS (
    SELECT *
    FROM report_hierarchy
    WHERE type = 0
),
rates_start AS (
    SELECT r.instid, r.curcode, r.avgrate
    FROM tr_cur_rate_hist r
    JOIN (
        SELECT instid, curcode, MAX(date) AS date
        FROM tr_cur_rate_hist
        WHERE instid = :instid
          AND date < (:year::int || '-01-01')::date
          AND curcode IS NOT NULL
        GROUP BY instid, curcode
    ) m
      ON m.instid = r.instid
     AND m.curcode = r.curcode
     AND m.date = r.date
    WHERE r.instid = :instid
),
month_end AS (
    SELECT (
        (:year::int || '-' || lpad((:period::int)::text, 2, '0') || '-01')::date
        + INTERVAL '1 month'
        - INTERVAL '1 day'
    )::date AS eom
),
rates_eom AS (
    SELECT r.instid, r.curcode, r.avgrate
    FROM tr_cur_rate_hist r
    JOIN (
        SELECT t.instid, t.curcode, MAX(t.date) AS date
        FROM tr_cur_rate_hist t
        CROSS JOIN month_end me
        WHERE t.instid = :instid
          AND t.date <= me.eom
          AND t.curcode IS NOT NULL
        GROUP BY t.instid, t.curcode
    ) m
      ON m.instid = r.instid
     AND m.curcode = r.curcode
     AND m.date = r.date
    WHERE r.instid = :instid
),
entry_filtered AS (
    SELECT
        e.instid,
        e.txndate,
        e.gl,
        trim(COALESCE(e.segcode, '00')) AS segcode,
        e.contgl,
        e.sign,
        e.txnamount,
        e.curcode
    FROM tr_glretail_entry e
    CROSS JOIN month_end me
    WHERE e.instid = :instid
      AND e.statusid = 1
      AND e.corr = 0
    --   AND (e.mark IS NULL OR e.mark <> 1)
      AND e.txndate >= ((:year::int - 1) || '-01-01')::date
      AND e.txndate <= me.eom
),
cont_txn AS (
    SELECT
        cl.id AS conf_column_id,
        CASE
            WHEN e.txndate < (:year::int || '-01-01')::date THEN 1
            ELSE 2
        END AS report_col,
        SUM(
            e.txnamount
            * COALESCE(cl.multiply, 1)
            * COALESCE(
                CASE
                    WHEN e.txndate < (:year::int || '-01-01')::date THEN rs.avgrate
                    ELSE re.avgrate
                END,
                1
            )
        ) AS amount
    FROM gl_report_conf_column_2 cl
    JOIN gl_report_conf_column_cont_txn ct
      ON ct.conf_column_id = cl.id
     AND ct.instid = cl.instid
     AND ct.statusid = 1
    JOIN entry_filtered e
      ON e.instid = cl.instid
     AND e.gl = substring(cl.acntno from 1 for 6)
     AND e.segcode = substring(cl.acntno from 7)
     AND e.contgl = substring(ct.contacntno from 1 for 6)
     AND e.segcode = substring(ct.contacntno from 7)
     AND (
            (ct.conttrantype = 'dt' AND e.sign = '-')
         OR (ct.conttrantype = 'ct' AND e.sign = '+')
     )
    LEFT JOIN rates_start rs
      ON rs.instid = e.instid
     AND rs.curcode = e.curcode
    LEFT JOIN rates_eom re
      ON re.instid = e.instid
     AND re.curcode = e.curcode
    WHERE cl.istranbal = 'cont'
      AND (
            (cl.columnidx = 1 AND e.txndate < (:year::int || '-01-01')::date)
         OR (cl.columnidx = 2 AND e.txndate >= (:year::int || '-01-01')::date)
      )
    GROUP BY
        cl.id,
        CASE
            WHEN e.txndate < (:year::int || '-01-01')::date THEN 1
            ELSE 2
        END
)

SELECT
    dt.id,
    dt.num,
    dt.name,

    round(COALESCE((
        SELECT SUM(
            CASE
                WHEN cl.istranbal = 'cont' THEN COALESCE(ctx.amount, 0)
                ELSE (
                    (
                        CASE
                            WHEN cl.istranbal = 'dt' THEN 0
                            WHEN cl.istranbal = 'ct' THEN 0
                            ELSE b.obal
                        END
                    )
                    + (CASE WHEN COALESCE(cl.isbegbal, 1) = 1 THEN 0 ELSE 1 END) * (
                        (CASE WHEN cl.istranbal = 'dt' THEN b.dt01 WHEN cl.istranbal = 'ct' THEN b.ct01 ELSE b.dt01 + b.ct01 END) +
                        (CASE WHEN cl.istranbal = 'dt' THEN b.dt02 WHEN cl.istranbal = 'ct' THEN b.ct02 ELSE b.dt02 + b.ct02 END) +
                        (CASE WHEN cl.istranbal = 'dt' THEN b.dt03 WHEN cl.istranbal = 'ct' THEN b.ct03 ELSE b.dt03 + b.ct03 END) +
                        (CASE WHEN cl.istranbal = 'dt' THEN b.dt04 WHEN cl.istranbal = 'ct' THEN b.ct04 ELSE b.dt04 + b.ct04 END) +
                        (CASE WHEN cl.istranbal = 'dt' THEN b.dt05 WHEN cl.istranbal = 'ct' THEN b.ct05 ELSE b.dt05 + b.ct05 END) +
                        (CASE WHEN cl.istranbal = 'dt' THEN b.dt06 WHEN cl.istranbal = 'ct' THEN b.ct06 ELSE b.dt06 + b.ct06 END) +
                        (CASE WHEN cl.istranbal = 'dt' THEN b.dt07 WHEN cl.istranbal = 'ct' THEN b.ct07 ELSE b.dt07 + b.ct07 END) +
                        (CASE WHEN cl.istranbal = 'dt' THEN b.dt08 WHEN cl.istranbal = 'ct' THEN b.ct08 ELSE b.dt08 + b.ct08 END) +
                        (CASE WHEN cl.istranbal = 'dt' THEN b.dt09 WHEN cl.istranbal = 'ct' THEN b.ct09 ELSE b.dt09 + b.ct09 END) +
                        (CASE WHEN cl.istranbal = 'dt' THEN b.dt10 WHEN cl.istranbal = 'ct' THEN b.ct10 ELSE b.dt10 + b.ct10 END) +
                        (CASE WHEN cl.istranbal = 'dt' THEN b.dt11 WHEN cl.istranbal = 'ct' THEN b.ct11 ELSE b.dt11 + b.ct11 END) +
                        (CASE WHEN cl.istranbal = 'dt' THEN b.dt12 WHEN cl.istranbal = 'ct' THEN b.ct12 ELSE b.dt12 + b.ct12 END)
                    )
                )
                * COALESCE(cl.multiply, 1)
                * COALESCE(rs.avgrate, 1)
            END
        )
        FROM gl_report_conf_column_2 cl
        LEFT JOIN gl_balance b
          ON b.instid = cl.instid
         AND b.account = cl.acntno
         AND b.year = (:year::int - 1)
         AND COALESCE(cl.istranbal, '') <> 'cont'
        LEFT JOIN rates_start rs
          ON rs.instid = b.instid
         AND rs.curcode = b.currency
        LEFT JOIN cont_txn ctx
          ON ctx.conf_column_id = cl.id
         AND ctx.report_col = 1
        WHERE cl.instid = dt.instid
          AND cl.root_conf_detail_id = dt.id
          AND cl.statusid = 1
          AND cl.columnidx = 1
          AND cl.type = 0
    ), 0), 2) AS col1,

    round(COALESCE((
        SELECT SUM(
            CASE
                WHEN cl.istranbal = 'cont' THEN COALESCE(ctx.amount, 0)
                ELSE (
                    (
                        CASE
                            WHEN cl.istranbal = 'dt' THEN 0
                            WHEN cl.istranbal = 'ct' THEN 0
                            ELSE b.obal
                        END
                    )
                    + (CASE WHEN COALESCE(cl.isbegbal, 1) = 1 THEN 0 ELSE 1 END) * (
                        CASE WHEN :period >= 1  THEN (CASE WHEN cl.istranbal = 'dt' THEN b.dt01 WHEN cl.istranbal = 'ct' THEN b.ct01 ELSE b.dt01 + b.ct01 END) ELSE 0 END +
                        CASE WHEN :period >= 2  THEN (CASE WHEN cl.istranbal = 'dt' THEN b.dt02 WHEN cl.istranbal = 'ct' THEN b.ct02 ELSE b.dt02 + b.ct02 END) ELSE 0 END +
                        CASE WHEN :period >= 3  THEN (CASE WHEN cl.istranbal = 'dt' THEN b.dt03 WHEN cl.istranbal = 'ct' THEN b.ct03 ELSE b.dt03 + b.ct03 END) ELSE 0 END +
                        CASE WHEN :period >= 4  THEN (CASE WHEN cl.istranbal = 'dt' THEN b.dt04 WHEN cl.istranbal = 'ct' THEN b.ct04 ELSE b.dt04 + b.ct04 END) ELSE 0 END +
                        CASE WHEN :period >= 5  THEN (CASE WHEN cl.istranbal = 'dt' THEN b.dt05 WHEN cl.istranbal = 'ct' THEN b.ct05 ELSE b.dt05 + b.ct05 END) ELSE 0 END +
                        CASE WHEN :period >= 6  THEN (CASE WHEN cl.istranbal = 'dt' THEN b.dt06 WHEN cl.istranbal = 'ct' THEN b.ct06 ELSE b.dt06 + b.ct06 END) ELSE 0 END +
                        CASE WHEN :period >= 7  THEN (CASE WHEN cl.istranbal = 'dt' THEN b.dt07 WHEN cl.istranbal = 'ct' THEN b.ct07 ELSE b.dt07 + b.ct07 END) ELSE 0 END +
                        CASE WHEN :period >= 8  THEN (CASE WHEN cl.istranbal = 'dt' THEN b.dt08 WHEN cl.istranbal = 'ct' THEN b.ct08 ELSE b.dt08 + b.ct08 END) ELSE 0 END +
                        CASE WHEN :period >= 9  THEN (CASE WHEN cl.istranbal = 'dt' THEN b.dt09 WHEN cl.istranbal = 'ct' THEN b.ct09 ELSE b.dt09 + b.ct09 END) ELSE 0 END +
                        CASE WHEN :period >= 10 THEN (CASE WHEN cl.istranbal = 'dt' THEN b.dt10 WHEN cl.istranbal = 'ct' THEN b.ct10 ELSE b.dt10 + b.ct10 END) ELSE 0 END +
                        CASE WHEN :period >= 11 THEN (CASE WHEN cl.istranbal = 'dt' THEN b.dt11 WHEN cl.istranbal = 'ct' THEN b.ct11 ELSE b.dt11 + b.ct11 END) ELSE 0 END +
                        CASE WHEN :period >= 12 THEN (CASE WHEN cl.istranbal = 'dt' THEN b.dt12 WHEN cl.istranbal = 'ct' THEN b.ct12 ELSE b.dt12 + b.ct12 END) ELSE 0 END
                    )
                )
                * COALESCE(cl.multiply, 1)
                * COALESCE(re.avgrate, 1)
            END
        )
        FROM gl_report_conf_column_2 cl
        LEFT JOIN gl_balance b
          ON b.instid = cl.instid
         AND b.account = cl.acntno
         AND b.year = :year::int
         AND COALESCE(cl.istranbal, '') <> 'cont'
        LEFT JOIN rates_eom re
          ON re.instid = b.instid
         AND re.curcode = b.currency
        LEFT JOIN cont_txn ctx
          ON ctx.conf_column_id = cl.id
         AND ctx.report_col = 2
        WHERE cl.instid = dt.instid
          AND cl.root_conf_detail_id = dt.id
          AND cl.statusid = 1
          AND cl.columnidx = 2
          AND cl.type = 0
    ), 0), 2) AS col2,

    dt.isbold,
    dt.instid
FROM gl_report_conf_detail dt
JOIN gl_report_conf_list li
  ON dt.instid = li.instid
 AND li.statusid = 1
 AND li.AC = 're080309'
WHERE dt.instid = :instid
  AND dt.statusid = 1
  AND dt.report_conf_id = li.id
ORDER BY dt.listorder;
