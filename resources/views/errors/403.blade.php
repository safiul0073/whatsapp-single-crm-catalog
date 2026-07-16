@extends('errors.layout')

@section('code', '403')
@section('title', __('Access Denied'))
@section('message', __($exception->getMessage() ?: 'You don\'t have permission to access this resource.'))

@section('icon', 'ph-shield-warning')
@section('icon-bg', 'bg-warning/10')
@section('icon-color', 'text-warning')
