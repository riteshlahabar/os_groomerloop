{{--
    The bundle's inner-page breadcrumb bar (`breadcrumb-bar`), as every non-home page in the design
    carries it.

    Shared by all three templates: the bundle itself has one services.html / about-us.html / etc.
    behind three different home pages, so the inner pages differ only in the header and footer
    chrome wrapped around them.
--}}
<div class="breadcrumb-bar">
    <div class="row">
        <div class="col-md-12 col-12">
            <nav aria-label="breadcrumb" class="page-breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ $site->urlFor(\Modules\Website\Domain\PageKey::Home) }}">
                            <i class="ti ti-home-2"></i>Home
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $site->pageTitle }}</li>
                </ol>
            </nav>
            <h1 class="breadcrumb-title">{{ $site->text('headline', $site->pageTitle) }}</h1>
        </div>
    </div>
</div>
