<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Bind the application TestCase to Feature tests so they boot the framework.
| RefreshDatabase is applied per-file via uses() in each test that needs the
| database.
|
*/

pest()->extend(TestCase::class)->in('Feature');
