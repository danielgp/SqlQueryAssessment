WITH
    CTE__ONE AS (
        SELECT
            *
        FROM
            CTE__ONE
    ),
CTE__SECOND AS (
SELECT
CURRENT_TIMESTAMP()
FROM
DUAL
)
SELECT
*
FROM
    CTE__SECOND
