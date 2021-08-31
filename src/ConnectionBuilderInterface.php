<?php

namespace mgboot\poolx;

interface ConnectionBuilderInterface
{
    public function buildConnection(): ConnectionInterface;
}
