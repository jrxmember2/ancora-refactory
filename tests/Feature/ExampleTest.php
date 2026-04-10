<?php

test('guests are redirected from the hub to login', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
