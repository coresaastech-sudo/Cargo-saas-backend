<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Gp\Entities\GpInstQual;
use Modules\Gp\Http\Requests\GpInstQualRequest;
use Modules\Gp\Entities\Views\VwGpInstQual;
use Illuminate\Support\Str;
use Modules\Dp\Entities\DpAccountType;
use Modules\Gp\Entities\GpActionCode;

class GpInstQualController extends Controller
{
    /**
     * Display a listing of the resource.
     * @AC gp015001
     * @return Response
     */
    public function index(Request $request)
    {
        $validate = $this->validateMe($request, [
            'prodcode' => 'required'
        ], [
            'prodcode.required' => "RC000011"
        ]);
        $instid = auth()->user()->instid;
        if (isset($request['instid']) && auth()->user()->isadmin == 1) $instid = $request['instid'];
        return $this->getGridData(
            $request,
            VwGpInstQual::where('instid', $instid)
                ->where('prodcode', $validate['prodcode'])
                ->where('statusid', 1)
                ->whereIn('txncode', ['dp901042', 'dp901060', 'dp901061', 'dp901062', 'ln902041', 'ln902042', 'ln902043'])
                ->orderBy('clscode', 'ASC')
                ->orderBy('txncode', 'ASC')
        );
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpInstQualRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        $data = [];
        $instid = $user->instid;
        if (isset($validated['instid']) && $user->isadmin == 1) $instid = $validated['instid'];
        $validated['statusid'] = 1;
        $validated['instid'] = $instid;
        $validated['created_by'] = $user->id;
        $validated['updated_by'] = $user->id;
        $validated['created_at'] = getNow();
        $validated['updated_at'] = getNow();

        if (!empty($validated['acntno1'])) {
            $validated['acnttype1'] = 'GL';
        };
        if (!empty($validated['acntno2'])) {
            $validated['acnttype2'] = 'GL';
        };
        $validated['clscode'] = $validated['clscode'] ?? 0;
        $code = Str::lower($validated['txncode']);
        $process = GpActionCode::where('ACTION_CODE', $code)->first();

        $data[] = $validated;
        $pref = substr($validated['txncode'], 0, 4);
        $adjtxn = 0;
        $captxn = 0;
        $corrtxn = 0;
        $capadjtxn = 0;
        $tmpdata = [];
        $tmpdata2 = [];
        if ($process->txnopt == 1) {
        } else if ($process->txnopt == 2) {
            $tmpdata = [
                'acntno1' => null,
                'acnttype1' => "00",
                'acntno2' => $validated['acntno1'],
                'acnttype2' => $validated['acnttype1'],
            ];

            $tmpdata2 = [
                'acntno1' => $validated['acntno1'],
                'acnttype1' => $validated['acnttype1'],
                'acntno2' => null,
                'acnttype2' => "00",
            ];
        } else if ($process->txnopt == 3) {
            $tmpdata = [
                'acntno1' => $validated['acntno2'],
                'acnttype1' => $validated['acnttype2'],
                'acntno2' => null,
                'acnttype2' => "00",
            ];

            $tmpdata2 = [
                'acntno1' => null,
                'acnttype1' => "00",
                'acntno2' => $validated['acntno2'],
                'acnttype2' => $validated['acnttype2'],
            ];
        }
        // /'dp901041', 'dp901042', 'dp901060', 'dp901061', 'dp901062', 'ln902041', 'ln902042', 'ln902043'/
        switch ($process->ACTION_CODE) {
            case 'dp901041':
                $adjtxn = 2;
                $captxn = 10;
                $capadjtxn = 12;
                $corrtxn = 100;
                break;
            case 'dp901042':
                $adjtxn = 2;
                $captxn = 10;
                $capadjtxn = 12;
                $corrtxn = 100;
                break;
            case 'dp901060':
                $adjtxn = -14;
                $captxn = 6;
                $capadjtxn = -4;
                $corrtxn = 100;
                break;
            case 'dp901061':
                $adjtxn = -14;
                $captxn = 6;
                $capadjtxn = -4;
                $corrtxn = 100;
                break;
            case 'dp901062':
                $adjtxn = -14;
                $captxn = 6;
                $capadjtxn = -4;
                $corrtxn = 100;
                break;
            case 'ln902041':
                $adjtxn = 3;
                $captxn = 10;
                $capadjtxn = 13;
                $corrtxn = 100;
                break;
            case 'ln902042':
                $adjtxn = 3;
                $captxn = 10;
                $capadjtxn = 13;
                $corrtxn = 100;
                break;
            case 'ln902043':
                $adjtxn = 3;
                $captxn = 10;
                $capadjtxn = 13;
                $corrtxn = 100;
                break;
        }
        // 2 Аккрул буцаалт
        if ($corrtxn <> 0) {
            $data[] = [
                'txncode' => ($pref) . (substr($validated['txncode'], -4) * 1 + $corrtxn),
                'prodcode' => $validated['prodcode'],
                'clscode' => $validated['clscode'] ?? 0,
                'acntno1' => $validated['acntno2'],
                'acnttype1' => $validated['acnttype2'],
                'acntno2' => $validated['acntno1'],
                'acnttype2' => $validated['acnttype1'],
                'statusid' => 1,
                'instid' => $instid,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'created_at' => getNow(),
                'updated_at' => getNow(),
            ];
            // 3 Аккрул залрууллага буцаалт
            if ($adjtxn <> 0) {
                $data[] = [
                    'txncode' => ($pref) . (substr($validated['txncode'], -4) * 1 + $adjtxn + $corrtxn),
                    'prodcode' => $validated['prodcode'],
                    'clscode' => $validated['clscode'] ?? 0,
                    'acntno1' => $validated['acntno2'],
                    'acnttype1' => $validated['acnttype2'],
                    'acntno2' => $validated['acntno1'],
                    'acnttype2' => $validated['acnttype1'],
                    'statusid' => 1,
                    'instid' => $instid,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'created_at' => getNow(),
                    'updated_at' => getNow(),
                ];
            }
        }
        // 4 Кап
        if ($captxn <> 0) {
            $data[] = [
                'txncode' => ($pref) . (substr($validated['txncode'], -4) * 1 + $captxn),
                'prodcode' => $validated['prodcode'],
                'clscode' => $validated['clscode'] ?? 0,
                'acntno1' => $tmpdata['acntno1'],
                'acnttype1' => $tmpdata['acnttype1'],
                'acntno2' => $tmpdata['acntno2'],
                'acnttype2' => $tmpdata['acnttype2'],
                'statusid' => 1,
                'instid' => $instid,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'created_at' => getNow(),
                'updated_at' => getNow(),
            ];
            // 5 Кап буцаалт
            if ($corrtxn <> 0) {
                $data[] = [
                    'txncode' => $pref . (substr($validated['txncode'], -4) * 1 + $corrtxn + $captxn),
                    'prodcode' => $validated['prodcode'],
                    'clscode' => $validated['clscode'] ?? 0,
                    'acntno1' => $tmpdata2['acntno1'],
                    'acnttype1' => $tmpdata2['acnttype1'],
                    'acntno2' => $tmpdata2['acntno2'],
                    'acnttype2' => $tmpdata2['acnttype2'],
                    'statusid' => 1,
                    'instid' => $instid,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'created_at' => getNow(),
                    'updated_at' => getNow(),
                ];
            }
        }
        // 6 Аккрул залрууллага
        if ($adjtxn <> 0) {
            $data[] = [
                'txncode' => $pref . (substr($validated['txncode'], -4) * 1 + $adjtxn),
                'prodcode' => $validated['prodcode'],
                'clscode' => $validated['clscode'] ?? 0,
                'acntno1' => $validated['acntno1'],
                'acnttype1' => $validated['acnttype1'],
                'acntno2' => $validated['acntno2'],
                'acnttype2' => $validated['acnttype2'],
                'statusid' => 1,
                'instid' => $instid,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'created_at' => getNow(),
                'updated_at' => getNow(),
            ];
            // 7 Кап залрууллага
            if ($captxn <> 0) {
                $data[] = [
                    'txncode' => ($pref) . (substr($validated['txncode'], -4) * 1 + $capadjtxn),
                    'prodcode' => $validated['prodcode'],
                    'clscode' => $validated['clscode'] ?? 0,
                    'acntno1' => $tmpdata['acntno1'],
                    'acnttype1' => $tmpdata['acnttype1'],
                    'acntno2' => $tmpdata['acntno2'],
                    'acnttype2' => $tmpdata['acnttype2'],
                    'statusid' => 1,
                    'instid' => $instid,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'created_at' => getNow(),
                    'updated_at' => getNow(),
                ];
                // 8 Кап залрууллага буцаалт
                if ($corrtxn <> 0) {
                    $data[] = [
                        'txncode' => $pref . (substr($validated['txncode'], -4) * 1 + $corrtxn + $capadjtxn),
                        'prodcode' => $validated['prodcode'],
                        'clscode' => $validated['clscode'] ?? 0,
                        'acntno1' => $tmpdata2['acntno1'],
                        'acnttype1' => $tmpdata2['acnttype1'],
                        'acntno2' => $tmpdata2['acntno2'],
                        'acnttype2' => $tmpdata2['acnttype2'],
                        'statusid' => 1,
                        'instid' => $instid,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                        'created_at' => getNow(),
                        'updated_at' => getNow(),
                    ];
                }
            }
        }
        if (Str::lower($process->moduleid) == 'dp') {
            $prod = DpAccountType::where('instid', $instid)
                ->where('statusid', 1)
                ->where('prodcode', $validated['prodcode'])
                ->where('procflag', 'T')
                ->first();
            if (!empty($prod)) {
                $data[] = [
                    'txncode' => 'dp901029',
                    'prodcode' => $validated['prodcode'],
                    'clscode' => $validated['clscode'] ?? 0,
                    'acntno1' => null,
                    'acnttype1' => '00',
                    'acntno2' => $validated['acntno1'],
                    'acnttype2' => $validated['acnttype1'],
                    'statusid' => 1,
                    'instid' => $instid,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'created_at' => getNow(),
                    'updated_at' => getNow(),
                ];
                $data[] = [
                    'txncode' => 'dp901030',
                    'prodcode' => $validated['prodcode'],
                    'clscode' => $validated['clscode'] ?? 0,
                    'acntno1' => $validated['acntno2'],
                    'acnttype1' => $validated['acnttype2'],
                    'acntno2' => $validated['acntno1'],
                    'acnttype2' => $validated['acnttype1'],
                    'statusid' => 1,
                    'instid' => $instid,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'created_at' => getNow(),
                    'updated_at' => getNow(),
                ];
                $data[] = [
                    'txncode' => 'dp901130',
                    'prodcode' => $validated['prodcode'],
                    'clscode' => $validated['clscode'] ?? 0,
                    'acntno1' => $validated['acntno1'],
                    'acnttype1' => $validated['acnttype1'],
                    'acntno2' => $validated['acntno2'],
                    'acnttype2' => $validated['acnttype2'],
                    'statusid' => 1,
                    'instid' => $instid,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'created_at' => getNow(),
                    'updated_at' => getNow(),
                ];
            }
        }
        GpInstQual::insert($data);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'nullable',
            'txncode' => 'nullable',
            'prodcode' => 'nullable',
        ]);
        $instid = auth()->user()->instid;

        if (empty($validated['id'])) {
            if (!empty($validated['txncode'])) {
                $GPinstqual = VwGpInstQual::where('txncode', $validated['txncode'])
                    ->where('prodcode', $validated['prodcode'])
                    ->where('clscode', 0)
                    ->where('instid', $instid)->where('statusid', 1)->first();
            } else {
                $this->error('RC000011');
            }
        } else {

            $GPinstqual = VwGpInstQual::where('id', $validated['id'])
                ->where('instid', $instid)->where('statusid', 1)->first();
            if (!$GPinstqual) {
                $this->error("RC000010", [
                    'id' => $validated['id']
                ]);
            }
        }

        return $GPinstqual;
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(GpInstQualRequest $request)
    {
        $validated = $request->validated();
        $instid = auth()->user()->instid;
        if (empty($validated['id'])) {
            if (!empty($validated['txncode'])) {
                $GPqual = GpInstQual::where('txncode', $validated['txncode'])
                    ->where('prodcode', $validated['prodcode'])
                    ->where('clscode', 0)
                    ->where('instid', $instid)->where('statusid', 1)->first();
            } else {
                $this->error('RC000011');
            }
        } else {
            $GPqual = GpInstQual::where('id', $validated['id'])
                ->where('instid', $instid)->where('statusid', 1)->first();
        }

        if (!$GPqual) {
            $this->error('RC000027');
        }
        $validated['prodcode'] = $GPqual->prodcode;
        $validated['txncode'] = $GPqual->txncode;
        $validated['clscode'] = $GPqual->clscode;
        $validated['updated_at'] = getNow();
        $validated['updated_by'] = auth()->user()->id;

        if (!empty($validated['acntno1'])) {
            $validated['acnttype1'] = 'GL';
        } else {
            $validated['acnttype1'] = '00';
        };
        if (!empty($validated['acntno2'])) {
            $validated['acnttype2'] = 'GL';
        } else {
            $validated['acnttype2'] = '00';
        };

        $code = Str::lower($validated['txncode']);
        $process = GpActionCode::where('ACTION_CODE', $code)->first();
        $tmpdata = [];
        $tmpdata2 = [];
        $adjtxn = 0;

        if ($process->txnopt == 1) {
        } else if ($process->txnopt == 2) {
            $tmpdata = [
                'acntno1' => null,
                'acnttype1' => "00",
                'acntno2' => $validated['acntno1'],
                'acnttype2' => $validated['acnttype1'],
            ];

            $tmpdata2 = [
                'acntno1' => $validated['acntno1'],
                'acnttype1' => $validated['acnttype1'],
                'acntno2' => null,
                'acnttype2' => "00",
            ];
        } else if ($process->txnopt == 3) {
            $tmpdata = [
                'acntno1' => $validated['acntno2'],
                'acnttype1' => $validated['acnttype2'],
                'acntno2' => null,
                'acnttype2' => "00",
            ];

            $tmpdata2 = [
                'acntno1' => null,
                'acnttype1' => "00",
                'acntno2' => $validated['acntno2'],
                'acnttype2' => $validated['acnttype2'],
            ];
        }

        switch ($process->ACTION_CODE) {
            case 'dp901041':
                $adjtxn = 2;
                $captxn = 10;
                $capadjtxn = 12;
                $corrtxn = 100;
                break;
            case 'dp901042':
                $adjtxn = 2;
                $captxn = 10;
                $capadjtxn = 12;
                $corrtxn = 100;
                break;
            case 'dp901060':
                $adjtxn = -14;
                $captxn = 6;
                $capadjtxn = -4;
                $corrtxn = 100;
                break;
            case 'dp901061':
                $adjtxn = -14;
                $captxn = 6;
                $capadjtxn = -4;
                $corrtxn = 100;
                break;
            case 'dp901062':
                $adjtxn = -14;
                $captxn = 6;
                $capadjtxn = -4;
                $corrtxn = 100;
                break;
            case 'ln902041':
                $adjtxn = 3;
                $captxn = 10;
                $capadjtxn = 13;
                $corrtxn = 100;
                break;
            case 'ln902042':
                $adjtxn = 3;
                $captxn = 10;
                $capadjtxn = 13;
                $corrtxn = 100;
                break;
            case 'ln902043':
                $adjtxn = 3;
                $captxn = 10;
                $capadjtxn = 13;
                $corrtxn = 100;
                break;
        }

        try {
            DB::beginTransaction();
            $GPqual->update($validated);

            $pref = substr($GPqual->txncode, 0, 4);
            // 2 Аккрул буцаалт
            if ($corrtxn <> 0) {
                $GPqual2 = GpInstQual::where('instid', $instid)
                    ->where('prodcode', $GPqual->prodcode)
                    ->where('clscode', $GPqual->clscode)
                    ->where('txncode', ($pref) . (substr($GPqual->txncode, -4) * 1 + $corrtxn))
                    ->where('statusid', 1)->first();
                if (!$GPqual2) {
                    $this->error('RC000027');
                }
                $GPqual2->update(
                    [
                        'acntno1' => $validated['acntno2'],
                        'acnttype1' => $validated['acnttype2'],
                        'acntno2' => $validated['acntno1'],
                        'acnttype2' => $validated['acnttype1'],
                        'statusid' => 1,
                        'updated_by' => auth()->user()->id,
                        'updated_at' => getNow(),
                    ]
                );
                // 3 Аккрул залрууллага буцаалт
                if ($adjtxn <> 0) {
                    $GPqual3 = GpInstQual::where('instid', $instid)
                        ->where('prodcode', $GPqual->prodcode)
                        ->where('clscode', $GPqual->clscode)
                        ->where('txncode', ($pref) . (substr($GPqual->txncode, -4) * 1 + $adjtxn + $corrtxn))
                        ->where('statusid', 1)->first();
                    if (!$GPqual3) {
                        $this->error('RC000027');
                    }
                    $GPqual3->update(
                        [
                            'acntno1' => $validated['acntno2'],
                            'acnttype1' => $validated['acnttype2'],
                            'acntno2' => $validated['acntno1'],
                            'acnttype2' => $validated['acnttype1'],
                            'statusid' => 1,
                            'updated_by' => auth()->user()->id,
                            'updated_at' => getNow(),
                        ]
                    );
                }
            }
            // 4 Кап
            if ($captxn <> 0) {
                $GPqual4 = GpInstQual::where('instid', $instid)
                    ->where('prodcode', $GPqual->prodcode)
                    ->where('clscode', $GPqual->clscode)
                    ->where('txncode', ($pref) . (substr($GPqual->txncode, -4) * 1 + $captxn))
                    ->where('statusid', 1)->first();
                if (!$GPqual4) {
                    $this->error('RC000027');
                }
                $GPqual4->update(
                    [
                        'acntno1' => $tmpdata['acntno1'],
                        'acnttype1' => $tmpdata['acnttype1'],
                        'acntno2' => $tmpdata['acntno2'],
                        'acnttype2' => $tmpdata['acnttype2'],
                        'statusid' => 1,
                        'updated_by' => auth()->user()->id,
                        'updated_at' => getNow(),
                    ]
                );
                // 5 Кап буцаалт
                if ($corrtxn <> 0) {
                    $GPqual5 = GpInstQual::where('instid', $instid)
                        ->where('prodcode', $GPqual->prodcode)
                        ->where('clscode', $GPqual->clscode)
                        ->where('txncode', ($pref) . (substr($GPqual->txncode, -4) * 1 + $corrtxn + $captxn))
                        ->where('statusid', 1)->first();
                    if (!$GPqual5) {
                        $this->error('RC000027');
                    }
                    $GPqual5->update(
                        [
                            'acntno1' => $tmpdata2['acntno1'],
                            'acnttype1' => $tmpdata2['acnttype1'],
                            'acntno2' => $tmpdata2['acntno2'],
                            'acnttype2' => $tmpdata2['acnttype2'],
                            'statusid' => 1,
                            'updated_by' => auth()->user()->id,
                            'updated_at' => getNow(),
                        ]
                    );
                }
            }
            // 6 Аккрул залрууллага
            if ($adjtxn <> 0) {
                $GPqual6 = GpInstQual::where('instid', $instid)
                    ->where('prodcode', $GPqual->prodcode)
                    ->where('clscode', $GPqual->clscode)
                    ->where('txncode', ($pref) . (substr($validated['txncode'], -4) * 1 + $adjtxn))
                    ->where('statusid', 1)->first();
                if (!$GPqual6) {
                    $this->error('RC000027');
                }
                $GPqual6->update(
                    [
                        'acntno1' => $validated['acntno1'],
                        'acnttype1' => $validated['acnttype1'],
                        'acntno2' => $validated['acntno2'],
                        'acnttype2' => $validated['acnttype2'],
                        'statusid' => 1,
                        'updated_by' => auth()->user()->id,
                        'updated_at' => getNow(),
                    ]
                );
                // 7 Кап залрууллага
                if ($captxn <> 0) {
                    $GPqual7 = GpInstQual::where('instid', $instid)
                        ->where('prodcode', $GPqual->prodcode)
                        ->where('clscode', $GPqual->clscode)
                        ->where('txncode', ($pref) . (substr($validated['txncode'], -4) * 1 + $capadjtxn))
                        ->where('statusid', 1)->first();
                    if (!$GPqual7) {
                        $this->error('RC000027');
                    }
                    $GPqual7->update(
                        [
                            'acntno1' => $tmpdata['acntno1'],
                            'acnttype1' => $tmpdata['acnttype1'],
                            'acntno2' => $tmpdata['acntno2'],
                            'acnttype2' => $tmpdata['acnttype2'],
                            'statusid' => 1,
                            'updated_by' => auth()->user()->id,
                            'updated_at' => getNow(),
                        ]
                    );
                    // 8 Кап залрууллага буцаалт
                    if ($corrtxn <> 0) {
                        $GPqual8 = GpInstQual::where('instid', $instid)
                            ->where('prodcode', $GPqual->prodcode)
                            ->where('clscode', $GPqual->clscode)
                            ->where('txncode', ($pref) . (substr($validated['txncode'], -4) * 1 + $corrtxn + $capadjtxn))
                            ->where('statusid', 1)->first();
                        if (!$GPqual8) {
                            $this->error('RC000027');
                        }
                        $GPqual8->update(
                            [
                                'acntno1' => $tmpdata2['acntno1'],
                                'acnttype1' => $tmpdata2['acnttype1'],
                                'acntno2' => $tmpdata2['acntno2'],
                                'acnttype2' => $tmpdata2['acnttype2'],
                                'statusid' => 1,
                                'updated_by' => auth()->user()->id,
                                'updated_at' => getNow(),
                            ]
                        );
                    }
                }
            }
            if (Str::lower($process->moduleid) == 'dp') {
                $prod = DpAccountType::where('instid', auth()->user()->instid)
                    ->where('statusid', 1)
                    ->where('prodcode', $validated['prodcode'])
                    ->where('procflag', 'T')
                    ->first();
                if (!empty($prod)) {
                    $GPqual11 = GpInstQual::where('instid', $instid)
                        ->where('prodcode', $GPqual->prodcode)
                        ->where('clscode', $GPqual->clscode)
                        ->where('txncode', 'dp901029')
                        ->where('statusid', 1)->first();
                    if (!$GPqual11) {
                        $this->error('RC000027');
                    }
                    $GPqual11->update(
                        [
                            'acntno1' => null,
                            'acnttype1' => '00',
                            'acntno2' => $validated['acntno1'],
                            'acnttype2' => $validated['acnttype1'],
                            'statusid' => 1,
                            'updated_by' => auth()->user()->id,
                            'updated_at' => getNow(),
                        ]
                    );
                    $GPqual9 = GpInstQual::where('instid', $instid)
                        ->where('prodcode', $GPqual->prodcode)
                        ->where('clscode', $GPqual->clscode)
                        ->where('txncode', 'dp901030')
                        ->where('statusid', 1)->first();
                    if (!$GPqual9) {
                        $this->error('RC000027');
                    }
                    $GPqual9->update(
                        [
                            'acntno1' => $validated['acntno2'],
                            'acnttype1' => $validated['acnttype2'],
                            'acntno2' => $validated['acntno1'],
                            'acnttype2' => $validated['acnttype1'],
                            'statusid' => 1,
                            'updated_by' => auth()->user()->id,
                            'updated_at' => getNow(),
                        ]
                    );
                    $GPqual10 = GpInstQual::where('instid', $instid)
                        ->where('prodcode', $GPqual->prodcode)
                        ->where('clscode', $GPqual->clscode)
                        ->where('txncode', 'dp901130')
                        ->where('statusid', 1)->first();
                    if (!$GPqual10) {
                        $this->error('RC000027');
                    }
                    $GPqual10->update(
                        [
                            'acntno1' => $validated['acntno1'],
                            'acnttype1' => $validated['acnttype1'],
                            'acntno2' => $validated['acntno2'],
                            'acnttype2' => $validated['acnttype2'],
                            'statusid' => 1,
                            'updated_by' => auth()->user()->id,
                            'updated_at' => getNow(),
                        ]
                    );
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'nullable',
            'txncode' => 'nullable',
            'prodcode' => 'nullable',
        ]);
        $instid = auth()->user()->instid;
        if (empty($validated['id'])) {
            if (!empty($validated['txncode'])) {
                $GPqual = GpInstQual::where('txncode', $validated['txncode'])
                    ->where('prodcode', $validated['prodcode'])
                    ->where('clscode', 0)
                    ->where('instid', $instid)->where('statusid', 1)->first();
            } else {
                $this->error('RC000011');
            }
        } else {
            $qualcount = GpInstQual::where('id', $validated['id'])
                ->where('instid', $instid)->count();
            $GPqual = GpInstQual::where('id', $validated['id'])
                ->where('instid', $instid)->where('statusid', 1)->first();
        }

        if (!$GPqual) {
            $this->error('RC000027');
        }

        $validated['txncode'] = $GPqual->txncode;
        $validated['statusid'] = ($qualcount + 2) * -1;
        $validated['updated_at'] = getNow();
        $validated['updated_by'] = auth()->user()->id;

        if (!empty($validated['acntno1'])) {
            $validated['acnttype1'] = 'GL';
        } else {
            $validated['acnttype1'] = '00';
        };
        if (!empty($validated['acntno2'])) {
            $validated['acnttype2'] = 'GL';
        } else {
            $validated['acnttype2'] = '00';
        };

        $code = Str::lower($validated['txncode']);
        $process = GpActionCode::where('ACTION_CODE', $code)->first();

        $adjtxn = 0;
        $captxn = 0;
        $capadjtxn = 0;
        $corrtxn = 0;


        switch ($process->ACTION_CODE) {
            case 'dp901041':
                $adjtxn = 2;
                $captxn = 10;
                $capadjtxn = 12;
                $corrtxn = 100;
                break;
            case 'dp901042':
                $adjtxn = 2;
                $captxn = 10;
                $capadjtxn = 12;
                $corrtxn = 100;
                break;
            case 'dp901060':
                $adjtxn = -14;
                $captxn = 6;
                $capadjtxn = -4;
                $corrtxn = 100;
                break;
            case 'dp901061':
                $adjtxn = -14;
                $captxn = 6;
                $capadjtxn = -4;
                $corrtxn = 100;
                break;
            case 'dp901062':
                $adjtxn = -14;
                $captxn = 6;
                $capadjtxn = -4;
                $corrtxn = 100;
                break;
            case 'ln902041':
                $adjtxn = 3;
                $captxn = 10;
                $capadjtxn = 13;
                $corrtxn = 100;
                break;
            case 'ln902042':
                $adjtxn = 3;
                $captxn = 10;
                $capadjtxn = 13;
                $corrtxn = 100;
                break;
            case 'ln902043':
                $adjtxn = 3;
                $captxn = 10;
                $capadjtxn = 13;
                $corrtxn = 100;
                break;
        }

        try {
            DB::beginTransaction();
            $GPqual->update($validated);

            $pref = substr($GPqual->txncode, 0, 4);
            // 2 Аккрул буцаалт
            if ($corrtxn <> 0) {
                $GPqual2 = GpInstQual::where('instid', $instid)
                    ->where('prodcode', $GPqual->prodcode)
                    ->where('clscode', $GPqual->clscode)
                    ->where('txncode', ($pref) . (substr($GPqual->txncode, -4) * 1 + $corrtxn))
                    ->where('statusid', 1)->first();
                if (!$GPqual2) {
                    $this->error('RC000027');
                }
                $GPqual2count = GpInstQual::where('instid', $instid)
                    ->where('prodcode', $GPqual->prodcode)
                    ->where('clscode', $GPqual->clscode)
                    ->where('txncode', ($pref) . (substr($GPqual->txncode, -4) * 1 + $corrtxn))
                    ->where('statusid', '!=', 1)->count();

                $GPqual2->update(
                    [
                        'statusid' => ($GPqual2count + 1) * -1,
                        'updated_by' => auth()->user()->id,
                        'updated_at' => getNow(),
                    ]
                );
                // 3 Аккрул залрууллага буцаалт
                if ($adjtxn <> 0) {
                    $GPqual3 = GpInstQual::where('instid', $instid)
                        ->where('prodcode', $GPqual->prodcode)
                        ->where('clscode', $GPqual->clscode)
                        ->where('txncode', ($pref) . (substr($GPqual->txncode, -4) * 1 + $adjtxn + $corrtxn))
                        ->where('statusid', 1)->first();
                    if (!$GPqual3) {
                        $this->error('RC000027');
                    }
                    $GPqual3count = GpInstQual::where('instid', $instid)
                        ->where('prodcode', $GPqual->prodcode)
                        ->where('clscode', $GPqual->clscode)
                        ->where('txncode', ($pref) . (substr($GPqual->txncode, -4) * 1 + $adjtxn + $corrtxn))
                        ->where('statusid', '!=', 1)->count();
                    $GPqual3->update(
                        [
                            'statusid' => ($GPqual3count + 1) * -1,
                            'updated_by' => auth()->user()->id,
                            'updated_at' => getNow(),
                        ]
                    );
                }
            }
            // 4 Кап
            if ($captxn <> 0) {
                $GPqual4 = GpInstQual::where('instid', $instid)
                    ->where('prodcode', $GPqual->prodcode)
                    ->where('clscode', $GPqual->clscode)
                    ->where('txncode', ($pref) . (substr($GPqual->txncode, -4) * 1 + $captxn))
                    ->where('statusid', 1)->first();
                if (!$GPqual4) {
                    $this->error('RC000027');
                }
                $GPqual4count = GpInstQual::where('instid', $instid)
                    ->where('prodcode', $GPqual->prodcode)
                    ->where('clscode', $GPqual->clscode)
                    ->where('txncode', ($pref) . (substr($GPqual->txncode, -4) * 1 + $captxn))
                    ->where('statusid', '!=', 1)->count();
                $GPqual4->update(
                    [
                        'statusid' => ($GPqual4count + 1) * -1,
                        'updated_by' => auth()->user()->id,
                        'updated_at' => getNow(),
                    ]
                );
                // 5 Кап буцаалт
                if ($corrtxn <> 0) {
                    $GPqual5 = GpInstQual::where('instid', $instid)
                        ->where('prodcode', $GPqual->prodcode)
                        ->where('clscode', $GPqual->clscode)
                        ->where('txncode', ($pref) . (substr($GPqual->txncode, -4) * 1 + $corrtxn + $captxn))
                        ->where('statusid', 1)->first();
                    if (!$GPqual5) {
                        $this->error('RC000027');
                    }

                    $GPqual5count = GpInstQual::where('instid', $instid)
                        ->where('prodcode', $GPqual->prodcode)
                        ->where('clscode', $GPqual->clscode)
                        ->where('txncode', ($pref) . (substr($GPqual->txncode, -4) * 1 + $corrtxn + $captxn))
                        ->where('statusid', '!=', 1)->count();

                    $GPqual5->update(
                        [
                            'statusid' => ($GPqual5count + 1) * -1,
                            'updated_by' => auth()->user()->id,
                            'updated_at' => getNow(),
                        ]
                    );
                }
            }
            // 6 Аккрул залрууллага
            if ($adjtxn <> 0) {
                $GPqual6 = GpInstQual::where('instid', $instid)
                    ->where('prodcode', $GPqual->prodcode)
                    ->where('clscode', $GPqual->clscode)
                    ->where('txncode', ($pref) . (substr($validated['txncode'], -4) * 1 + $adjtxn))
                    ->where('statusid', 1)->first();

                $GPqual6count = GpInstQual::where('instid', $instid)
                    ->where('prodcode', $GPqual->prodcode)
                    ->where('clscode', $GPqual->clscode)
                    ->where('txncode', ($pref) . (substr($validated['txncode'], -4) * 1 + $adjtxn))
                    ->where('statusid', '!=', 1)->count();
                if (!$GPqual6) {
                    $this->error('RC000027');
                }
                $GPqual6->update(
                    [
                        'statusid' => ($GPqual6count + 1) * -1,
                        'updated_by' => auth()->user()->id,
                        'updated_at' => getNow(),
                    ]
                );
                // 7 Кап залрууллага
                if ($captxn <> 0) {
                    $GPqual7 = GpInstQual::where('instid', $instid)
                        ->where('prodcode', $GPqual->prodcode)
                        ->where('clscode', $GPqual->clscode)
                        ->where('txncode', ($pref) . (substr($validated['txncode'], -4) * 1 + $capadjtxn))
                        ->where('statusid', 1)->first();
                    if (!$GPqual7) {
                        $this->error('RC000027');
                    }

                    $GPqual7count = GpInstQual::where('instid', $instid)
                        ->where('prodcode', $GPqual->prodcode)
                        ->where('clscode', $GPqual->clscode)
                        ->where('txncode', ($pref) . (substr($validated['txncode'], -4) * 1 + $capadjtxn))
                        ->where('statusid', '!=', 1)->count();
                    $GPqual7->update(
                        [
                            'statusid' => ($GPqual7count + 1) * -1,
                            'updated_by' => auth()->user()->id,
                            'updated_at' => getNow(),
                        ]
                    );
                    // 8 Кап залрууллага буцаалт
                    if ($corrtxn <> 0) {
                        $GPqual8 = GpInstQual::where('instid', $instid)
                            ->where('prodcode', $GPqual->prodcode)
                            ->where('clscode', $GPqual->clscode)
                            ->where('txncode', ($pref) . (substr($validated['txncode'], -4) * 1 + $corrtxn + $capadjtxn))
                            ->where('statusid', 1)->first();
                        if (!$GPqual8) {
                            $this->error('RC000027');
                        }
                        $GPqual8count = GpInstQual::where('instid', $instid)
                            ->where('prodcode', $GPqual->prodcode)
                            ->where('clscode', $GPqual->clscode)
                            ->where('txncode', ($pref) . (substr($validated['txncode'], -4) * 1 + $corrtxn + $capadjtxn))
                            ->where('statusid', '!=', 1)->count();
                        $GPqual8->update(
                            [
                                'statusid' => ($GPqual8count + 1) * -1,
                                'updated_by' => auth()->user()->id,
                                'updated_at' => getNow(),
                            ]
                        );
                    }
                }
            }
            if (Str::lower($process->moduleid) == 'dp') {
                $prod = DpAccountType::where('instid', auth()->user()->instid)
                    ->where('statusid', 1)
                    ->where('prodcode', $GPqual->prodcode)
                    ->where('procflag', 'T')
                    ->first();
                if (!empty($prod)) {
                    $GPqual11 = GpInstQual::where('instid', $instid)
                        ->where('prodcode', $GPqual->prodcode)
                        ->where('clscode', $GPqual->clscode)
                        ->where('txncode', 'dp901029')
                        ->where('statusid', 1)->first();
                    if (!$GPqual11) {
                        $this->error('RC000027');
                    }

                    $GPqual11count = GpInstQual::where('instid', $instid)
                        ->where('prodcode', $GPqual->prodcode)
                        ->where('clscode', $GPqual->clscode)
                        ->where('txncode', 'dp901029')
                        ->where('statusid', '!=', 1)->count();
                    $GPqual11->update(
                        [
                            'statusid' => ($GPqual11count + 1) * -1,
                            'updated_by' => auth()->user()->id,
                            'updated_at' => getNow(),
                        ]
                    );
                    $GPqual9 = GpInstQual::where('instid', $instid)
                        ->where('prodcode', $GPqual->prodcode)
                        ->where('clscode', $GPqual->clscode)
                        ->where('txncode', 'dp901030')
                        ->where('statusid', 1)->first();
                    if (!$GPqual9) {
                        $this->error('RC000027');
                    }

                    $GPqual9count = GpInstQual::where('instid', $instid)
                        ->where('prodcode', $GPqual->prodcode)
                        ->where('clscode', $GPqual->clscode)
                        ->where('txncode', 'dp901030')
                        ->where('statusid', '!=', 1)->count();

                    $GPqual9->update(
                        [
                            'statusid' => ($GPqual9count + 1) * -1,
                            'updated_by' => auth()->user()->id,
                            'updated_at' => getNow(),
                        ]
                    );
                    $GPqual10 = GpInstQual::where('instid', $instid)
                        ->where('prodcode', $GPqual->prodcode)
                        ->where('clscode', $GPqual->clscode)
                        ->where('txncode', 'dp901130')
                        ->where('statusid', 1)->first();
                    if (!$GPqual10) {
                        $this->error('RC000027');
                    }

                    $GPqual10count = GpInstQual::where('instid', $instid)
                        ->where('prodcode', $GPqual->prodcode)
                        ->where('clscode', $GPqual->clscode)
                        ->where('txncode', 'dp901130')
                        ->where('statusid', '!=', 1)->count();
                    $GPqual10->update(
                        [
                            'statusid' => ($GPqual10count + 1) * -1,
                            'updated_by' => auth()->user()->id,
                            'updated_at' => getNow(),
                        ]
                    );
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
