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
                    if(strpos($row[2],"http")==false){
                        $row[2]="http://".$row[2];
                    }
                    $parse = parse_url($row[2]);
                    if(isset($parse['host'])) {
                        $host = str_replace('www.', '', $parse['host']);
                        $url = $parse['scheme'].'://'.$parse['host'];
                        if (Company::where('url', 'like', "%{$host}%")->count() === 0) {
                            $company = Company::create([
                                'source'        => $row[0],
                                'name'          => $row[1],
                                'url'           => $url,
                                'contact_form_url'           => $row[3],
                                'area'          => $row[4]
                            ]);
    
                            if (isset($row[5]) && $row[5] != '') {
                                $company->phones()->updateOrCreate([
                                    'phone'         => $row[5]
                                ]);
                            }
                        } 
                    }else { 
                        $host = str_replace('www.', '', $row[2]);
                        if (Company::where('url', 'like', "%{$host}%")->count() === 0) {
                            $company = Company::create([
                                'source'        => $row[0],
                                'name'          => $row[1],
                                'url'           => $row[2],
                                'contact_form_url'           => $row[3],
                                'area'          => $row[4]
                            ]);

                            if (isset($row[5]) && $row[5] != '') {
                                $company->phones()->updateOrCreate([
                                    'phone'         => $row[5]
                                ]);
                            }
                        }
                    }
                   
                }
                
            } catch (\Throwable $e) {
                dd($e->getMessage());
                continue;
            }
           
        }
    }
}