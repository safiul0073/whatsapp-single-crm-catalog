<?php

use App\Modules\Chatbots\Http\Controllers\PublicChatbotWidgetController;
use Illuminate\Support\Facades\Route;

Route::get('widgets/chatbot/{token}/loader.js', [PublicChatbotWidgetController::class, 'loader'])->name('widgets.chatbot.loader');
Route::options('widgets/chatbot/{token}/{path?}', [PublicChatbotWidgetController::class, 'options'])->where('path', '.*')->name('widgets.chatbot.options');
Route::get('widgets/chatbot/{token}', [PublicChatbotWidgetController::class, 'config'])->name('widgets.chatbot.config');
Route::post('widgets/chatbot/{token}/sessions', [PublicChatbotWidgetController::class, 'session'])->name('widgets.chatbot.sessions');
Route::post('widgets/chatbot/{token}/messages', [PublicChatbotWidgetController::class, 'message'])->name('widgets.chatbot.messages');
Route::get('widgets/chatbot/{token}/sessions/{session}/messages', [PublicChatbotWidgetController::class, 'messages'])->name('widgets.chatbot.sessions.messages');
