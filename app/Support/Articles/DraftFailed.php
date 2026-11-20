<?php

namespace App\Support\Articles;

use RuntimeException;

/** A draft could not be written, for a reason worth showing the editor as it is. */
class DraftFailed extends RuntimeException {}
