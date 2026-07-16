@extends('errors.layout')

@section('code', '429')
@section('title', __('Too Many Requests'))
@section('message', __('You\'ve made too many requests. Please wait a moment and try again.'))

@section('icon', 'ph-traffic-signal')
@section('icon-bg', 'bg-warning/10')
@section('icon-color', 'text-warning')
