<?php

namespace App\Imports;

use App\Models\Company;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class CompanyImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $key => $row) 
        {
            if ($key === 0 || $row[0] === 'カテゴリ') {
                continue;
            }

            try {
                $category = \App\Models\Source::where('name', $row[0])->first();
                if (empty($category)) {
                    $lastCategory = \App\Models\Source::orderByDesc('sort_no')->first();
		if(!isset($lastCategory)){
$category = \App\Models\Source::create([
                        'name'          => $row[0],
                        'sort_no'       => 1
                    ]);
}else{
$category = \App\Models\Source::create([
                        'name'          => $row[0],
                        'sort_no'       => $lastCategory->sort_no + 1
                    ]);
}
                    
                }
                if (isset($category)) {
                    $parse = parse_url($row[2]);
                    $host = str_replace('www.', '', $parse['host']);
                    if (Company::where('url', 'like', "%{$host}%")->count() === 0) {
                        $company = Company::create([
                            'source'        => $row[0],
                            'name'          => $row[1],
                            'url'           => $row[2],
                            'area'          => $row[3],
                            'is_origin_email' => 1
                        ]);

                        

                        if (isset($row[5]) && $row[5] != '') {
                            $company->phones()->updateOrCreate([
                                'phone'         => $row[5]
                            ]);
                        }
                    } else {
                        // $company = Company::where('url', 'like', "%{$host}%")->first();
                        // $company->update([
                        //     'area' => $row[3],
                        //     'source' => $category->sort_no,
                        //     'name' => $row[1]
                        // ]);

                        // if (isset($row[4]) && $row[4] != '') {
                        //     if ($company->emails()->count() > 0) {
                        //         $company->emails()->first()->update([
                        //             'email'         => $row[4],
                        //         ]);
                        //     } else {
                        //         $company->emails()->updateOrCreate([
                        //             'email'         => $row[4],
                        //             'is_verified'   => 1
                        //         ]);
                        //     }
                        // }

                        // if (isset($row[5]) && $row[5] != '') {
                        //     if ($company->phones()->count() > 0) {
                        //         $company->phones()->first()->update([
                        //             'phone'         => $row[5],
                        //         ]);
                        //     } else {
                        //         $company->phones()->updateOrCreate([
                        //             'phone'         => $row[5]
                        //         ]);
                        //     }
                        // }
                    }
                }
            } catch (\Throwable $e) {
dd($e->getMessage());
            }
        }
    }
}