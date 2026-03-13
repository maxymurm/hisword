package org.androidbible.domain.usecase

import kotlinx.coroutines.async
import kotlinx.coroutines.coroutineScope
import org.androidbible.domain.model.SearchResult
import org.androidbible.domain.repository.BibleReaderFactory

/**
 * Searches across all engine readers in parallel,
 * merges results, and tags each with its engine type.
 */
class SearchUseCase(
    private val readerFactory: BibleReaderFactory,
) {
    data class EngineSearchResult(
        val result: SearchResult,
        val engine: String,
        val moduleKey: String,
    )

    /**
     * Search all provided modules in parallel.
     * Returns merged results sorted by ARI.
     */
    suspend fun search(
        modules: List<Pair<String, String>>, // (moduleKey, engine)
        query: String,
        maxPerModule: Int = 50,
    ): List<EngineSearchResult> = coroutineScope {
        val jobs = modules.map { (key, engine) ->
            async {
                val reader = readerFactory.readerFor(engine)
                val results = reader.search(key, query, maxPerModule)
                results.map { EngineSearchResult(it, engine, key) }
            }
        }

        jobs.flatMap { it.await() }.sortedBy { it.result.verse.ari }
    }
}
