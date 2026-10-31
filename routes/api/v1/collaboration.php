<?php

use App\Http\Controllers\Api\V1\BoardController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\MeetingNoteController;
use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\QuickLinkController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Collaboration
|--------------------------------------------------------------------------
|
| Open to every authenticated user, whatever their role — this is the part of
| the product developers and QA actually work in. Nothing here carries role
| middleware; per-record ownership is enforced by policies (CommentPolicy,
| EventPolicy, NotePolicy, MeetingNotePolicy, QuickLinkPolicy) inside the
| controllers, and collections are scoped by the models' visibleTo() scopes.
|
*/

/*
 | Tasks & subtasks.
 */
Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
Route::post('tasks/{task}/subtasks', [TaskController::class, 'storeSubtask'])->name('tasks.subtasks.store');

/*
 | Comments on tasks. (Release comments live in the releases routes.)
 */
Route::get('tasks/{task}/comments', [CommentController::class, 'indexForTask'])->name('tasks.comments.index');
Route::post('tasks/{task}/comments', [CommentController::class, 'storeForTask'])->name('tasks.comments.store');
Route::put('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

/*
 | Kanban board.
 */
Route::get('board', [BoardController::class, 'index'])->name('board.index');
Route::post('board/tasks', [BoardController::class, 'storeTask'])->name('board.tasks.store');
Route::patch('board/tasks/{task}', [BoardController::class, 'move'])->name('board.move');

/*
 | Calendar events.
 */
Route::get('events', [EventController::class, 'index'])->name('events.index');
Route::post('events', [EventController::class, 'store'])->name('events.store');
Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');
Route::put('events/{event}', [EventController::class, 'update'])->name('events.update');
Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

/*
 | Daily notes — private, shared, or shared with specific people.
 */
Route::get('notes', [NoteController::class, 'index'])->name('notes.index');
Route::post('notes', [NoteController::class, 'store'])->name('notes.store');
Route::get('notes/{note}', [NoteController::class, 'show'])->name('notes.show');
Route::put('notes/{note}', [NoteController::class, 'update'])->name('notes.update');
Route::delete('notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');

/*
 | Meeting notes — release-linked or general.
 */
Route::get('meeting-notes', [MeetingNoteController::class, 'index'])->name('meeting-notes.index');
Route::post('meeting-notes', [MeetingNoteController::class, 'store'])->name('meeting-notes.store');
Route::get('meeting-notes/{meetingNote}', [MeetingNoteController::class, 'show'])->name('meeting-notes.show');
Route::put('meeting-notes/{meetingNote}', [MeetingNoteController::class, 'update'])->name('meeting-notes.update');
Route::delete('meeting-notes/{meetingNote}', [MeetingNoteController::class, 'destroy'])->name('meeting-notes.destroy');

/*
 | Quick links.
 */
Route::get('quick-links', [QuickLinkController::class, 'index'])->name('quick-links.index');
Route::post('quick-links', [QuickLinkController::class, 'store'])->name('quick-links.store');
Route::put('quick-links/{quickLink}', [QuickLinkController::class, 'update'])->name('quick-links.update');
Route::post('quick-links/{quickLink}/pin', [QuickLinkController::class, 'pin'])->name('quick-links.pin');
Route::delete('quick-links/{quickLink}', [QuickLinkController::class, 'destroy'])->name('quick-links.destroy');
