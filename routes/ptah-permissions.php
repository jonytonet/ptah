<?php

use Illuminate\Support\Facades\Route;
use Ptah\Livewire\Permission\AuditList;
use Ptah\Livewire\Permission\DepartmentList;
use Ptah\Livewire\Permission\PageList;
use Ptah\Livewire\Permission\PermissionGuide;
use Ptah\Livewire\Permission\RoleList;
use Ptah\Livewire\Permission\UserPermissionList;

// These screens administer the access-control system itself — master-only.
Route::middleware(['web', 'auth', 'ptah.master'])->group(function () {
    Route::get('/ptah-departments', DepartmentList::class)->name('ptah.acl.departments');
    Route::get('/ptah-roles', RoleList::class)->name('ptah.acl.roles');
    Route::get('/ptah-pages', PageList::class)->name('ptah.acl.pages');
    Route::get('/ptah-users-acl', UserPermissionList::class)->name('ptah.acl.users');
    Route::get('/ptah-audit', AuditList::class)->name('ptah.acl.audit');
    Route::get('/ptah-permission-guide', PermissionGuide::class)->name('ptah.acl.guide');
});
