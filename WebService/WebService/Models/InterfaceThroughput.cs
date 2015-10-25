using System;
using System.Collections.Generic;
using System.Linq;
using System.Web;

namespace WebService.Models
{

    public class InterfaceThroughput
    {
        /// <summary>
        /// Ingress in bps
        /// </summary>
        public Int64 Ingress { get; set; }

        /// <summary>
        /// Egress in bps
        /// </summary>
        public Int64 Egress { get; set; }

        /// <summary>
        /// When should the request be made again? In seconds (not ms).
        /// </summary>
        public int Interval { get; set; }

        /// <summary>
        /// Time of the request
        /// </summary>
        public DateTime Time { get; set; }
    }
}