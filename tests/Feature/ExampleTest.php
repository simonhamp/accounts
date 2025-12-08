<?php

test('home redirects to admin', function () {
    $response = $this->get('/');

    $response->assertRedirect('/admin');
});