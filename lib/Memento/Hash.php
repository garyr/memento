<?php
/**
 * Memento Hash class
 *
 * @author Gary Rogers <gmrwebde@gmail.com>
 */

namespace Memento;

class Hash
{
    /**
     * Used for field name to store the serialized data object on a hash
     */
    const FIELD_DATA 	= 'data';

    /**
     * Used for field name to store expires data on a hash
     */
    const FIELD_EXPIRES	= 'expires';

    /**
     * Used for field name to store keys for non-hash set drivers (e.g. memcache)
     */
    const FIELD_KEYS 	= 'keys';

    /**
     * Used for field name to store created data on a hash
     */
    const FIELD_CREATED	= 'created';

    /**
     * Used for field name to store valid flag on a hash
     */
    const FIELD_VALID 	= 'valid';
}