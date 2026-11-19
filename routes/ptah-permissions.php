<?php

use Illuminate\Support\Facades\Route;
use Ptah\Livewire\Permission\AuditList;
use Ptah\Livewire\Permission\DepartmentList;
use Ptah\Livewire\Permission\PageList;
use Ptah\Livewire\Permission\PermissionGuide;
use Ptah\Livewire\Permission\RoleList;
use Ptah\Livewire\Permission\UserPermissionList;
use Ptah\Support\StackPage;

// These screens administer the access-control system itself — master-only.
Route::middleware(['web', 'auth', 'ptah.master'])->group(function () {
    Route::get('/ptah-departments', StackPage::action('departments', DepartmentList::class))->name('ptah.acl.departments');
    Route::get('/ptah-roles', StackPage::action('roles', RoleList::class))->name('ptah.acl.roles');
    Route::get('/ptah-pages', StackPage::action('pages', PageList::class))->name('ptah.acl.pages');
    Route::get('/ptah-users-acl', StackPage::action('access-users', UserPermissionList::class))->name('ptah.acl.users');
    Route::get('/ptah-audit', StackPage::action('access-audit', AuditList::class))->name('ptah.acl.audit');
    Route::get('/ptah-permission-guide', StackPage::action('permission-guide', PermissionGuide::class))->name('ptah.acl.guide');
});
