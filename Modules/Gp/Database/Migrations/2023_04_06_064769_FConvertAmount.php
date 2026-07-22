<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("CREATE OR REPLACE FUNCTION SPELLAMOUNT(pdecimal NUMERIC)
            RETURNS VARCHAR AS $$
            DECLARE
                stext VARCHAR := '';
                pint NUMERIC;
                psubdecimal NUMERIC;
            BEGIN
                RETURN SPELLAMOUNT(pdecimal, 'mn');
            END;
            $$ LANGUAGE plpgsql;");

        DB::statement("CREATE OR REPLACE FUNCTION SPELLAMOUNT(pdecimal NUMERIC, plang CHAR)
            RETURNS VARCHAR AS $$
            DECLARE
                stext VARCHAR := '';
                pint NUMERIC;
                psubdecimal NUMERIC;
            BEGIN
                psubdecimal := pdecimal - TRUNC(pdecimal);
                pint := TRUNC(pdecimal);
                stext := DECIMAL2TEXT(pint, FALSE, plang, TRUE);

                IF LENGTH(stext) > 0 THEN
                    stext := stext || CASE plang WHEN 'en' THEN 'tögrög' ELSE 'төгрөг' END;
                END IF;

                IF psubdecimal <> 0 THEN
                    psubdecimal := CAST(SUBSTRING(CAST(psubdecimal AS VARCHAR), 3, 2) AS NUMERIC);
                    stext := stext || ', ' || DECIMAL2TEXT(psubdecimal, FALSE, plang, TRUE) || CASE plang WHEN 'en' THEN 'möngö' ELSE 'мөнгө' END;
                END IF;

                stext := CONVERTNAME(stext, plang);

                -- Capitalize the first letter
                stext := UPPER(SUBSTRING(stext, 1, 1)) || SUBSTRING(stext, 2);

                RETURN stext;
            END;
            $$ LANGUAGE plpgsql;");
        DB::statement("CREATE OR REPLACE FUNCTION SPELLAMOUNTWCURCODE(pdecimal NUMERIC, pcurcode VARCHAR)
            RETURNS VARCHAR AS $$
            DECLARE
                stext VARCHAR := '';
                pint NUMERIC;
                psubdecimal NUMERIC;
            BEGIN
                RETURN SPELLAMOUNTWCURCODE(pdecimal, pcurcode, 'mn');
            END;
            $$ LANGUAGE plpgsql;");
        DB::statement("CREATE OR REPLACE FUNCTION SPELLAMOUNTWCURCODE(pdecimal NUMERIC, pcurcode VARCHAR, plang CHAR)
            RETURNS VARCHAR AS $$
            DECLARE
                stext VARCHAR := '';
                pint NUMERIC;
                psubdecimal NUMERIC;
            BEGIN
                psubdecimal := pdecimal - TRUNC(pdecimal);
                pint := TRUNC(pdecimal);
                stext := DECIMAL2TEXT(pint, FALSE, plang, TRUE) || CURCODE2TEXT(pcurcode, 1, plang);

                IF psubdecimal <> 0 THEN
                    psubdecimal := CAST(SUBSTRING(CAST(psubdecimal AS VARCHAR), 3, 2) AS NUMERIC);
                    stext := stext || ', ' || DECIMAL2TEXT(psubdecimal, FALSE, plang, TRUE) || CURCODE2TEXT(pcurcode, 0, plang);
                END IF;

                stext := CONVERTNAME(stext, plang);

                -- Capitalize the first letter
                stext := UPPER(SUBSTRING(stext, 1, 1)) || SUBSTRING(stext, 2);

                RETURN stext;
            END;
            $$ LANGUAGE plpgsql;");
        DB::statement("CREATE OR REPLACE FUNCTION CURCODE2TEXT(pcurcode VARCHAR, pismain INTEGER)
            RETURNS VARCHAR AS $$
            DECLARE
                stext VARCHAR := '';
            BEGIN
                RETURN CURCODE2TEXT(pcurcode, pismain, 'mn');
            END;
            $$ LANGUAGE plpgsql;");
        DB::statement("CREATE OR REPLACE FUNCTION CURCODE2TEXT(pcurcode VARCHAR, pismain INTEGER, plang CHAR)
            RETURNS VARCHAR AS $$
            DECLARE
                stext VARCHAR := '';
            BEGIN
                CASE
                    WHEN pismain = 1 THEN
                        CASE pcurcode
                            WHEN 'MNT' THEN stext := CASE plang WHEN 'en' THEN 'tögrög' ELSE 'төгрөг' END;
                            WHEN 'USD' THEN stext := CASE plang WHEN 'en' THEN 'U.S dollar' ELSE 'ам.доллар' END;
                            WHEN 'RUB' THEN stext := CASE plang WHEN 'en' THEN 'ruble' ELSE 'рубль' END;
                            WHEN 'CNY' THEN stext := CASE plang WHEN 'en' THEN 'yuan' ELSE 'юань' END;
                            WHEN 'EUR' THEN stext := CASE plang WHEN 'en' THEN 'euro' ELSE 'евро' END;
                            WHEN 'KRW' THEN stext := CASE plang WHEN 'en' THEN 'won' ELSE 'вон' END;
                            WHEN 'GBP' THEN stext := CASE plang WHEN 'en' THEN 'pound' ELSE 'паунд' END;
                            WHEN 'JPY' THEN stext := CASE plang WHEN 'en' THEN 'yen' ELSE 'иен' END;
                            ELSE stext := CASE plang WHEN 'en' THEN 'tögrög' ELSE 'төгрөг' END;
                        END CASE;
                    ELSE
                        CASE pcurcode
                            WHEN 'MNT' THEN stext := CASE plang WHEN 'en' THEN 'möngö' ELSE 'мөнгө' END;
                            WHEN 'USD' THEN stext := CASE plang WHEN 'en' THEN 'cent' ELSE 'цент' END;
                            WHEN 'RUB' THEN stext := CASE plang WHEN 'en' THEN 'kopek' ELSE 'копейк' END;
                            WHEN 'CNY' THEN stext := CASE plang WHEN 'en' THEN 'jiao' ELSE 'мо' END;
                            WHEN 'EUR' THEN stext := CASE plang WHEN 'en' THEN 'cent' ELSE 'цент' END;
                            WHEN 'KRW' THEN stext := CASE plang WHEN 'en' THEN 'chon' ELSE 'чон' END;
                            WHEN 'GBP' THEN stext := CASE plang WHEN 'en' THEN 'penny' ELSE 'пенс' END;
                            WHEN 'JPY' THEN stext := CASE plang WHEN 'en' THEN 'sen' ELSE 'сэн' END;
                            ELSE stext := CASE plang WHEN 'en' THEN 'tögrög' ELSE 'төгрөг' END;
                        END CASE;
                END CASE;

                RETURN stext;
            END;
            $$ LANGUAGE plpgsql;");

        DB::statement("CREATE OR REPLACE FUNCTION DECIMAL2TEXT(pint NUMERIC, pIsScale BOOLEAN, pIsAmount BOOLEAN)
            RETURNS VARCHAR AS $$
            BEGIN
                RETURN DECIMAL2TEXT(pint, pIsScale, 'mn', pIsAmount);
            END;
            $$ LANGUAGE plpgsql;");
        DB::statement("CREATE OR REPLACE FUNCTION DECIMAL2TEXT(pint NUMERIC, pIsScale BOOLEAN, plang CHAR, pIsAmount BOOLEAN)
            RETURNS VARCHAR AS $$
            DECLARE
                terms1 VARCHAR[];
                terms2 VARCHAR[];
                terms4 VARCHAR[];
                terms5 VARCHAR[];
                terms6 VARCHAR[];
                terms0 VARCHAR[];

                c INTEGER := 0;
                i NUMERIC;
                v1 NUMERIC;
                v2 NUMERIC;
                d1 NUMERIC;
                d2 NUMERIC;
                d3 NUMERIC;

                s1 VARCHAR := '';
                s2 VARCHAR := '';
                s3 VARCHAR := '';
            BEGIN
                CASE plang
                    WHEN 'en' THEN
                        terms1 := ARRAY['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'];
                        terms2 := ARRAY['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'];
                        terms4 := ARRAY['', 'ten', 'twenty', 'fifty', 'fourty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
                        terms5 := ARRAY['hundred', 'thousand', 'million', 'billion', 'trillion', 'quadrillion', 'quintillion', 'sextillion'];
                        terms6 := ARRAY['hundred', 'thousand', 'million', 'billion', 'trillion', 'quadrillion', 'quintillion', 'sextillion'];
                        terms0 := ARRAY['of ten', 'of hundred', 'of thousand', 'of million', 'of billion', 'of trillion', 'of quadrillion', 'of quintillion', 'of sextillion'];
                    ELSE
                        terms1 := ARRAY['тэг', 'нэг', 'хоёр', 'гурав', 'дөрөв', 'тав', 'зургаа', 'долоо', 'найм', 'ес'];
                        terms2 := ARRAY['тэг', 'нэгэн', 'хоёр', 'гурван', 'дөрвөн', 'таван', 'зургаан', 'долоон', 'найман', 'есөн'];
                        terms4 := ARRAY['', 'арван', 'хорин', 'гучин', 'дөчин', 'тавин', 'жаран', 'далан', 'наян', 'ерэн'];
                        terms5 := ARRAY['зуун', 'мянга', 'сая', 'тэрбум', 'их наяд', 'тунамал', 'их ингүүмэл', 'ялгаруулагч'];
                        terms6 := ARRAY['зуу', 'мянган', 'сая', 'тэрбум', 'их наяд', 'тунамал', 'их ингүүмэл', 'ялгаруулагч'];
                        terms0 := ARRAY['аравны', 'зууны', 'мянганы', 'саяны', 'тэрбумны', 'их наядийн', 'тунамалын', 'их ингүүмэлийн', 'ялгаруулагчийн'];
                END CASE;

                i := pint;
                RAISE NOTICE 'Converted input value to i: %', i;
                IF pint IS NULL THEN
                    RETURN NULL;
                END IF;

                IF pIsScale = TRUE THEN
                    s3 := ', ' || terms0[array_length(terms0, 1)] || ' ';
                END IF;

                IF pint = 0 THEN
                    s3 := terms1[1] || ' ';
                END IF;

                WHILE (i <> 0) LOOP
                    s1 := '';
                    v1 := MOD(i, 1000);
                    i := TRUNC(i / 1000);

                    IF (v1 = 1) THEN
                        s1 := s1 || terms1[2] || ' ';
                    ELSE
                        d1 := TRUNC(v1 / 100);

                        IF (d1 > 0) THEN
                            IF (d1 = 1) THEN
                                s1 := s1 || terms1[2] || ' ';
                            ELSE
                                s1 := s1 || terms2[d1 + 1] || ' ';
                            END IF;

                            s1 := s1 || terms5[1] || ' ';
                        END IF;

                        v2 := v1 - d1 * 100;
                        d2 := TRUNC(v2 / 10);

                        IF (d2 > 0) THEN
                            s1 := s1 || terms4[d2 + 1] || ' ';
                        END IF;

                        d3 := v2 - d2 * 10;
                        RAISE NOTICE 'Processing v1: %; d3: %; i: %;', v1, d3, i;
                        IF (d3 > 0) THEN
                            IF (c = 0 AND pIsAmount = FALSE) THEN
                                s1 := s1 || terms1[d3 + 1] || ' ';
                            ELSE
                                s1 := s1 || terms2[d3 + 1] || ' ';
                            END IF;
                        END IF;
                    END IF;

                    IF (v1 > 0 AND c > 0) THEN
                        IF (LENGTH(s2) > 0) THEN
                            s1 := s1 || terms5[c + 1] || ' ';
                        ELSE
                            s1 := s1 || terms6[c + 1] || ' ';
                        END IF;
                    END IF;

                    s2 := s1 || s2;
                    c := c + 1;
                END LOOP;

                s3 := s3 || s2;

                RETURN s3;
            EXCEPTION
                WHEN OTHERS THEN
                    RAISE NOTICE 'pk_spellamount.decimal2text exp: % - %', SQLSTATE, SQLERRM;
                    RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;");
        DB::statement("CREATE OR REPLACE FUNCTION SPELLNUMBER(pdecimal NUMERIC)
            RETURNS VARCHAR AS $$
            DECLARE
                stext VARCHAR := '';
                pint NUMERIC;
                psubdecimal NUMERIC;
            BEGIN
                RETURN SPELLNUMBER(pdecimal, 'mn');
            END;
            $$ LANGUAGE plpgsql;");
        DB::statement("CREATE OR REPLACE FUNCTION SPELLNUMBER(pdecimal NUMERIC, plang CHAR)
            RETURNS VARCHAR AS $$
            DECLARE
                stext VARCHAR := '';
                pint NUMERIC;
                psubdecimal NUMERIC;
            BEGIN
                psubdecimal := pdecimal - TRUNC(pdecimal);
                pint := TRUNC(pdecimal);
                stext := DECIMAL2TEXT(pint, FALSE, plang, FALSE);

                psubdecimal := TRUNC((psubdecimal-TRUNC(psubdecimal)) * 100);

                IF psubdecimal <> 0 THEN
                    IF (LENGTH(CAST(psubdecimal AS VARCHAR)) = 1) THEN
                        stext := stext || (CASE plang WHEN 'en' THEN 'of ten ' ELSE 'аравны ' END) || DECIMAL2TEXT(psubdecimal, FALSE, plang, FALSE);
                    ELSE
                        IF (LENGTH(CAST(psubdecimal AS VARCHAR)) = 2) THEN
                            stext := stext || (CASE plang WHEN 'en' THEN 'of hundred ' ELSE 'зууны ' END) || DECIMAL2TEXT(psubdecimal, FALSE, plang, FALSE);
                        ELSE
                            stext := stext || (CASE plang WHEN 'en' THEN 'of thousand ' ELSE 'мянганы ' END) || DECIMAL2TEXT(psubdecimal, FALSE, plang, FALSE);
                        END IF;
                    END IF;
                END IF;

                stext := CONVERTNAME(stext, plang);

                RETURN stext;
            END;
            $$ LANGUAGE plpgsql;");
        DB::statement("CREATE OR REPLACE FUNCTION CONVERTNAME(ptext VARCHAR, plang CHAR)
            RETURNS VARCHAR AS $$
            DECLARE
                stext VARCHAR := ptext;
            BEGIN
                IF plang = 'en' THEN
                    BEGIN
                        IF POSITION('ten one' IN stext) > 0 THEN
                            stext := REPLACE(stext, 'ten one', 'eleven');
                        ELSIF POSITION('ten two' IN stext) > 0 THEN
                            stext := REPLACE(stext, 'ten two', 'twelve');
                        ELSIF POSITION('ten three' IN stext) > 0 THEN
                            stext := REPLACE(stext, 'ten three', 'thirteen');
                        ELSIF POSITION('ten four' IN stext) > 0 THEN
                            stext := REPLACE(stext, 'ten four', 'fourteen');
                        ELSIF POSITION('ten five' IN stext) > 0 THEN
                            stext := REPLACE(stext, 'ten five', 'fifteen');
                        ELSIF POSITION('ten six' IN stext) > 0 THEN
                            stext := REPLACE(stext, 'ten six', 'sixteen');
                        ELSIF POSITION('ten seven' IN stext) > 0 THEN
                            stext := REPLACE(stext, 'ten seven', 'seventeen');
                        ELSIF POSITION('ten eight' IN stext) > 0 THEN
                            stext := REPLACE(stext, 'ten eight', 'eighteen');
                        ELSIF POSITION('ten nine' IN stext) > 0 THEN
                            stext := REPLACE(stext, 'ten nine', 'nineteen');
                        END IF;
                    END;
                END IF;

                RETURN stext;
            END;
            $$ LANGUAGE plpgsql;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP FUNCTION SPELLAMOUNT(NUMERIC)");
        DB::statement("DROP FUNCTION SPELLAMOUNT(NUMERIC, CHARACTER)");
        DB::statement("DROP FUNCTION SPELLAMOUNTWCURCODE(NUMERIC, VARCHAR)");
        DB::statement("DROP FUNCTION SPELLAMOUNTWCURCODE(NUMERIC, VARCHAR, CHARACTER)");
        DB::statement("DROP FUNCTION CURCODE2TEXT(VARCHAR, INTEGER)");
        DB::statement("DROP FUNCTION CURCODE2TEXT(VARCHAR, INTEGER, CHARACTER)");
        DB::statement("DROP FUNCTION DECIMAL2TEXT(NUMERIC, BOOLEAN, BOOLEAN)");
        DB::statement("DROP FUNCTION DECIMAL2TEXT(NUMERIC, BOOLEAN, CHARACTER, BOOLEAN)");
        DB::statement("DROP FUNCTION SPELLNUMBER(NUMERIC)");
        DB::statement("DROP FUNCTION SPELLNUMBER(NUMERIC, CHARACTER)");
        DB::statement("DROP FUNCTION CONVERTNAME(VARCHAR, CHARACTER)");
    }
};
