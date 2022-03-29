@extends('layouts.admin.app')
<style>

.time {
  margin:.375rem .5rem;
}

.content {
  padding-top: 50px;
  padding-bottom: 50px;
}

.content > .row {
  padding-right: 20% !important;
  padding-left: 20% !important;
}

.row > label {
  text-align: center;
  border-bottom: 1px solid #14a83b;
  margin: 0;
  padding: 20px;
}
.row > .content {
  border-bottom: 1px solid gray;
  color: gray;
  padding: 20px;
}
 </style>
@section('content_header_label')
    <h3 class="m-0">会社概要</h3>
@stop

@section('content')
  <div class="card">
    <div class="card-body">
      <div class="content">
        <div class="row">
          <label class="col-sm-4">社名</label>
          <div class="col-sm-8 content">
          ネクストワン株式会社
          </div>
        </div>
        <div class="row">
          <label class="col-sm-4">英文社名</label>
          <div class="col-sm-8 content">
          Next One Inc,
          </div>
        </div>
        <div class="row">
          <label class="col-sm-4">設立</label>
          <div class="col-sm-8 content">
          令和3年2月9日設立
          </div>
        </div>
        <div class="row">
          <label class="col-sm-4">資本金</label>
          <div class="col-sm-8 content">
          300万円
          </div>
        </div>
        <div class="row">
          <label class="col-sm-4">本社所在地</label>
          <div class="col-sm-8 content">
          〒300-0028
          <br>
          茨城県土浦市おおつ野８丁目２４−１６６−７
          </div>
        </div>
        <div class="row">
          <label class="col-sm-4">URL</label>
          <div class="col-sm-8 content">
            <a href="https://nextone-k.co.jp/">https://nextone-k.co.jp/</a>
          </div>
        </div>
      </div>
    </div>
  </div>
@stop
