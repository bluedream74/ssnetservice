<?php

namespace App\Http\Controllers\Admin;

use App\Models\ContactTemplate;
use Illuminate\Http\Request;

class ContactTemplateController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
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
            'fu_surname'        => 'required',
            'fu_lastname'       => 'required',
            'email'             => 'required',
            'title'             => 'required',
            'content'           => 'required',
            'homepageUrl'       => 'required',
            'postalCode1'       => 'required',
            'postalCode2'       => 'required',
            'phoneNumber1'      => 'required',
            'phoneNumber2'      => 'required',
            'phoneNumber3'      => 'required',
            'company'           => 'required',
        ]);

        $data = $request->only('surname', 'lastname', 'fu_surname', 'fu_lastname', 'email', 'title', 'myurl', 'content', 'homepageUrl', 'area', 'postalCode1', 'postalCode2', 'address', 'phoneNumber1', 'phoneNumber2', 'phoneNumber3', 'company');

        $contactTemplate = ContactTemplate::create($data);

        return back()->with(['system.message.success' => '追加しました。']);
    }

    /**
     * Display the specified resource.
     *
     * @param \App\ContactTemplate $contactTemplate
     *
     * @return \Illuminate\Http\Response
     */
    public function show(ContactTemplate $contactTemplate)
    {
        $prefectures = array();
        foreach (config('values.prefectures') as $value) {
            $prefectures[$value] = $value;
        }

        return view('admin.contact_template_edit', compact('contactTemplate', 'prefectures'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\ContactTemplate $contactTemplate
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(ContactTemplate $contactTemplate)
    {
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \App\ContactTemplate $contactTemplate
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ContactTemplate $contactTemplate)
    {
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\ContactTemplate $contactTemplate
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(ContactTemplate $contactTemplate, Request $request)
    {
        $request->validate([
            'id' => 'required'
        ]);
        try {
            ContactTemplate::findOrFail($request->id)->delete();

            return back()->with(['system.message.success' => '削除しました。']);
        } catch (\Exception $e) {
            return back()->with(['system.message.error' => '見つかりませんでした。']);
        }
    }
}
