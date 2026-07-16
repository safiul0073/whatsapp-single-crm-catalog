@extends('errors.layout')

@section('code', '404')
@section('title', __('Page Not Found'))
@section('message', __('The page you are looking for doesn\'t exist or has been moved.'))

@section('icon', 'ph-magnifying-glass')
@section('icon-bg', 'bg-info/10')
@section('icon-color', 'text-info')
