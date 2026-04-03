<?php

namespace App\Observers;

use App\Jobs\DownloadPaperJob;
use App\Models\Recherche\P5Treffer;

class P5TrefferObserver
{
    public function created(P5Treffer $treffer): void
    {
        if (blank($treffer->doi)) {
            return;
        }

        $treffer->update(['retrieval_status' => 'pending']);

        DownloadPaperJob::dispatch($treffer->id);
    }
}
