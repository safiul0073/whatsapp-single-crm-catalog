@extends('errors.layout')

@section('code', '503')
@section('title', __('Under Maintenance'))
@section('message', __('We\'re performing scheduled maintenance. We\'ll be back shortly.'))

@section('icon', 'ph-wrench')
@section('icon-bg', 'bg-secondary/10')
@section('icon-color', 'text-secondary')
