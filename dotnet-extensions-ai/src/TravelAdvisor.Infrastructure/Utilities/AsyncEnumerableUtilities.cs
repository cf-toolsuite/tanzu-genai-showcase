using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace TravelAdvisor.Infrastructure.Utilities
{
    /// <summary>
    /// Utility classes for working with async enumerables
    /// </summary>
    public static class AsyncEnumerableUtilities
    {
        /// <summary>
        /// Helper class for empty async enumerable results
        /// </summary>
        public class EmptyAsyncEnumerable<T> : IAsyncEnumerable<T>
        {
            public static readonly EmptyAsyncEnumerable<T> Instance = new();

            private EmptyAsyncEnumerable() { }

            public IAsyncEnumerator<T> GetAsyncEnumerator(CancellationToken cancellationToken = default)
            {
                return new EmptyAsyncEnumerator();
            }

            private class EmptyAsyncEnumerator : IAsyncEnumerator<T>
            {
                public T Current => default!;

                public ValueTask<bool> MoveNextAsync()
                {
                    return new ValueTask<bool>(false);
                }

                public ValueTask DisposeAsync()
                {
                    return ValueTask.CompletedTask;
                }
            }
        }
    }
}
