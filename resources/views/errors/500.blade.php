@extends('errors.layout')

@section('code', '500')
@section('title', __('Server Error'))
@section('message', __('Something went wrong on our end. Please try again later or contact support.'))

@section('icon', 'ph-bug')
@section('icon-bg', 'bg-error/10')
@section('icon-color', 'text-error')
