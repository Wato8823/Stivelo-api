<?php

namespace App\Http\Requests\Admin;

/**
 * Mise à jour d'un modèle : mêmes règles que la création, avec une exception
 * d'unicité sur le slug (gérée par StoreProductRequest::slugUniqueRule).
 */
class UpdateProductRequest extends StoreProductRequest {}
