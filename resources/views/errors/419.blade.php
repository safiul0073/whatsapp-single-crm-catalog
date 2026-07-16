@extends('errors.layout')

@section('code', '419')
@section('title', __('Session Expired'))
@section('message', __('Your session has expired. Please refresh the page and try again.'))

@section('icon', 'ph-clock-countdown')
@section('icon-bg', 'bg-warning/10')
@section('icon-color', 'text-warning')
