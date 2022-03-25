<?php

namespace App\Http\Controllers\Admin;

use App\Models\ContactTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContactTemplateController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Support\Facades\View
     */
    public function index()
    {
        $prefectures = array();
        foreach (config('values.prefectures') as $value) {
            $prefectures[$value] = $value;
        }
        $templates = ContactTemplate::all();

        return view('admin.contact_template', compact('templates', 'prefectures'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'surname'           => 'required',
            'lastname'          => 'required',
            'email'             => 'required',
            'template_title'    => 'required',
            'title'             => 'required',
            'content'           => 'required',
            'company'           => 'required',
        ]);

        $data = $request->only('template_title', 'surname', 'lastname', 'fu_surname', 'fu_lastname', 'email', 'title', 'myurl', 'content', 'homepageUrl', 'area', 'postalCode1', 'postalCode2', 'address', 'phoneNumber1', 'phoneNumber2', 'phoneNumber3', 'company');

        $contactTemplate = ContactTemplate::create($data);

        return back()->with(['system.message.success' => '追加しました。']);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\ContactTemplate $contactTemplate
     *
     * @return \Illuminate\Support\Facades\View
     */
    public function edit(ContactTemplate $contactTemplate)
    {
        $prefectures = array();
        foreach (config('values.prefectures') as $value) {
            $prefectures[$value] = $value;
        }

        return view('admin.contact_template_edit', compact('contactTemplate', 'prefectures'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \App\ContactTemplate $contactTemplate
     */
    public function update(Request $request, contactTemplate $contactTemplate)
    {
        try {
            $contactTemplate->update($request->all());

            return back()->with(['system.message.success' => '更新されました。']);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return back()->with(['system.message.error' => '更新できませんでした。']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy()
    {
        try {
            ContactTemplate::findOrFail(request()->get('id'))->delete();

            return back()->with(['system.message.success' => '削除しました。']);
        } catch (\Exception $e) {
            return back()->with(['system.message.error' => '見つかりませんでした。']);
        }
    }
}
