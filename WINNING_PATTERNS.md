# Nobingo Winning Pattern Engine

## 1. Architectural Philosophy

Winning conditions in Nobingo are **completely represented as data**, never hard-coded in controllers or frontend React components.

- A pattern is defined as a list of 5x5 board coordinates: `[[row, col], ...]`, where `row ∈ [0, 4]` and `col ∈ [0, 4]`.
- The center position `[2, 2]` represents the `FREE` square and is considered automatically marked in every game.
- Completed patterns are tracked as distinct entities, preventing double-counting.
- Single-pattern, multi-pattern (e.g., 2 Lines, 3 Lines), and custom combinations are evaluated using the unified `WinningPatternService`.

---

## 2. Standard Pattern Geometries (75-Ball)

### Horizontal Lines (Rows)
- **Row 1**: `[[0,0], [0,1], [0,2], [0,3], [0,4]]`
- **Row 2**: `[[1,0], [1,1], [1,2], [1,3], [1,4]]`
- **Row 3**: `[[2,0], [2,1], [2,2], [2,3], [2,4]]` *(includes FREE center)*
- **Row 4**: `[[3,0], [3,1], [3,2], [3,3], [3,4]]`
- **Row 5**: `[[4,0], [4,1], [4,2], [4,3], [4,4]]`

### Vertical Lines (Columns)
- **Column B**: `[[0,0], [1,0], [2,0], [3,0], [4,0]]`
- **Column I**: `[[0,1], [1,1], [2,1], [3,1], [4,1]]`
- **Column N**: `[[0,2], [1,2], [2,2], [3,2], [4,2]]` *(includes FREE center)*
- **Column G**: `[[0,3], [1,3], [2,3], [3,3], [4,3]]`
- **Column O**: `[[0,4], [1,4], [2,4], [3,4], [4,4]]`

### Diagonals
- **Main Diagonal**: `[[0,0], [1,1], [2,2], [3,3], [4,4]]` *(top-left to bottom-right)*
- **Reverse Diagonal**: `[[0,4], [1,3], [2,2], [3,1], [4,0]]` *(top-right to bottom-left)*

### Special & Full Card
- **Four Corners**: `[[0,0], [0,4], [4,0], [4,4]]`
- **X Bingo**: Both diagonals intersecting at center (9 cells).
- **Full Card (Coverall / Blackout)**: All 25 cells.

---

## 3. `WinningPatternService` API

### `getMarkedGrid(array $cardGrid, array $calledNumbers): array`
Constructs the 5×5 boolean state where a cell is marked if the card's number at that position has been called by the server or if it is the center cell `[2, 2]` (`FREE`).

### `isPatternCompleted(WinningPattern $pattern, array $markedGrid): bool`
Verifies whether every coordinate in the pattern has been marked.

### `getCompletedPatterns(iterable $patterns, array $markedGrid): Collection`
Returns all distinct completed patterns.

### `evaluate(iterable $allowedPatterns, int $requiredCount, array $cardGrid, array $calledNumbers): array`
Evaluates the winning status, returning:
```php
[
    'is_winner' => bool,
    'completed_count' => int,
    'required_count' => int,
    'completed_patterns' => Collection<WinningPattern>,
    'completed_slugs' => string[],
    'marked_grid' => bool[5][5],
]
```

---

## 4. Multi-Tenant Custom Patterns

Companies can define proprietary custom patterns (e.g. Plus Sign, Postage Stamp, Diamond) via the Web UI at `/c/{company_slug}/admin/patterns`. Custom patterns are strictly scoped to the tenant (`company_id`) and validated against 5×5 coordinate boundaries.
